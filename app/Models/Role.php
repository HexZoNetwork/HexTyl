<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property bool $is_system_role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Role extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'role';

    /**
     * The table associated with the model.
     */
    protected $table = 'roles';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'is_system_role',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'is_system_role' => 'boolean',
    ];

    /**
     * Validation rules to assign to this model.
     */
    public static array $validationRules = [
        'name' => 'required|string|max:191|unique:roles,name',
        'description' => 'nullable|string',
        'is_system_role' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function scopes(): HasMany
    {
        return $this->hasMany(RoleScope::class);
    }
}
