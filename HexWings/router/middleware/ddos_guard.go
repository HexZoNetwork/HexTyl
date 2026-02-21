package middleware

import (
	"net"
	"net/http"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/apex/log"
	"github.com/gin-gonic/gin"
	"golang.org/x/time/rate"

	"github.com/pterodactyl/wings/config"
)

type ddosIPState struct {
	limiter      *rate.Limiter
	strikes      int
	blockedUntil time.Time
	lastSeenAt   time.Time
}

// WingsDDoSGuard applies request limiting and temporary IP blocks for HTTP abuse.
func WingsDDoSGuard() gin.HandlerFunc {
	cfg := config.Get().Api.DDoS
	if !cfg.Enabled {
		return func(c *gin.Context) { c.Next() }
	}

	perIPPerMinute := maxInt(cfg.PerIPPerMinute, 30)
	perIPBurst := maxInt(cfg.PerIPBurst, 1)
	globalPerMinute := maxInt(cfg.GlobalPerMinute, perIPPerMinute)
	globalBurst := maxInt(cfg.GlobalBurst, perIPBurst)
	strikeThreshold := maxInt(cfg.StrikeThreshold, 1)
	blockSeconds := maxInt(cfg.BlockSeconds, 30)

	globalLimiter := rate.NewLimiter(rate.Limit(float64(globalPerMinute)/60.0), globalBurst)
	blockDuration := time.Duration(blockSeconds) * time.Second

	whitelist := parseCIDRWhitelist(cfg.Whitelist)
	states := make(map[string]*ddosIPState, 512)
	var statesMu sync.Mutex
	var reqCounter uint64

	return func(c *gin.Context) {
		clientIP := strings.TrimSpace(c.ClientIP())
		if clientIP == "" {
			clientIP = "unknown"
		}

		if ip := net.ParseIP(clientIP); ip != nil && ipInCIDRList(ip, whitelist) {
			c.Next()
			return
		}

		now := time.Now()
		statesMu.Lock()
		state, ok := states[clientIP]
		if !ok {
			state = &ddosIPState{
				limiter:    rate.NewLimiter(rate.Limit(float64(perIPPerMinute)/60.0), perIPBurst),
				lastSeenAt: now,
			}
			states[clientIP] = state
		} else {
			state.lastSeenAt = now
		}

		if now.Before(state.blockedUntil) {
			remaining := int(state.blockedUntil.Sub(now).Seconds())
			statesMu.Unlock()
			c.Header("Retry-After", "60")
			c.AbortWithStatusJSON(http.StatusTooManyRequests, gin.H{
				"error":              "Request blocked by Wings anti-DDoS guard.",
				"retry_after_seconds": remaining,
			})
			return
		}

		allowed := state.limiter.Allow() && globalLimiter.Allow()
		if !allowed {
			state.strikes++
			currentStrikes := state.strikes
			if state.strikes >= strikeThreshold {
				state.blockedUntil = now.Add(blockDuration)
				state.strikes = 0
			}
			statesMu.Unlock()

			log.WithFields(log.Fields{
				"subsystem": "http_ddos_guard",
				"ip":        clientIP,
				"path":      c.FullPath(),
				"method":    c.Request.Method,
				"strikes":   currentStrikes,
			}).Warn("request denied by wings anti-ddos guard")

			c.Header("Retry-After", "60")
			c.AbortWithStatusJSON(http.StatusTooManyRequests, gin.H{
				"error": "Too many requests. Please slow down.",
			})
			return
		}

		if state.strikes > 0 {
			state.strikes--
		}
		statesMu.Unlock()

		// Opportunistic cleanup to avoid unbounded map growth under high churn.
		if atomic.AddUint64(&reqCounter, 1)%1024 == 0 {
			cleanupBefore := now.Add(-3 * blockDuration)
			statesMu.Lock()
			for ip, st := range states {
				if st.lastSeenAt.Before(cleanupBefore) && now.After(st.blockedUntil) {
					delete(states, ip)
				}
			}
			statesMu.Unlock()
		}

		c.Next()
	}
}

func parseCIDRWhitelist(entries []string) []*net.IPNet {
	parsed := make([]*net.IPNet, 0, len(entries))
	for _, entry := range entries {
		candidate := strings.TrimSpace(entry)
		if candidate == "" {
			continue
		}

		if ip := net.ParseIP(candidate); ip != nil {
			maskBits := 128
			if ip.To4() != nil {
				maskBits = 32
			}
			parsed = append(parsed, &net.IPNet{
				IP:   ip,
				Mask: net.CIDRMask(maskBits, maskBits),
			})
			continue
		}

		if _, cidr, err := net.ParseCIDR(candidate); err == nil {
			parsed = append(parsed, cidr)
		}
	}

	return parsed
}

func ipInCIDRList(ip net.IP, entries []*net.IPNet) bool {
	for _, entry := range entries {
		if entry.Contains(ip) {
			return true
		}
	}

	return false
}

func maxInt(value, fallback int) int {
	if value < fallback {
		return fallback
	}

	return value
}
