# Security Policy

## Supported Versions

HexTyl only provides security support for the latest `major.minor` versions of the Panel and Wings software.
If a security vulnerability is found in an older version but cannot be reproduced on a supported version it will
not be considered. Additionally, security issues found in unreleased code will be addressed, but do not warrant a
security advisory.

For example, if the latest version of the Panel is `1.2.5` then we only support security reports for issues that
occur on `>= 1.2.x` versions of the Panel software. The Panel and Wings have their own versions, but they generally
follow eachother.

## Reporting a Vulnerability

Please use GitHub Security Advisories to quickly alert the team to any security issues you come across.
If that is not available, open an issue with minimal details and ask for a private reporting channel.

We make every effort to respond as soon as possible, although it may take a day or two for us to sync internally and
determine the severity of the report and its impact. Please, _do not_ use a public facing channel or GitHub issues to
report sensitive security issues.

As part of our process, we will create a security advisory for the affected versions and disclose it publicly, usually
two to four weeks after a releasing a version that addresses it.

## Local API Hardening Notes (This Workspace)

This workspace also includes runtime API hardening controls:

- Request payload hardening middleware for API routes.
- Root-only `/api/rootapplication/*` namespace.
- Admin API key creation capped by admin scopes and restricted to `Read/None`.

See `docs/API_HARDENING_AND_ROOTAPPLICATION.md` for implementation details.
