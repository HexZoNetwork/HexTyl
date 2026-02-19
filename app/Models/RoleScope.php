<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $role_id
 * @property string $scope
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class RoleScope extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'role_scope';

    /**
     * The table associated with the model.
     */
    protected $table = 'role_scopes';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'role_id',
        'scope',
    ];

    /**
     * Validation rules to assign to this model.
     */
    public static array $validationRules = [
        'role_id' => 'required|exists:roles,id',
        'scope' => 'required|string|max:191',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
