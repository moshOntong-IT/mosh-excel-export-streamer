<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Mosh\ExcelExportStreamer\Contracts\ExportableInterface;

/**
 * Example User model implementing ExportableInterface
 * Copy this to your Laravel app's app/Models/ directory or modify existing User model
 */
class User extends Authenticatable implements ExportableInterface
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Define which columns should be exported
     */
    public function getExportColumns(): array
    {
        return ['id', 'name', 'email', 'email_verified_at', 'created_at'];
    }

    /**
     * Define human-readable headers for export
     */
    public function getExportHeaders(): array
    {
        return [
            'ID',
            'Full Name', 
            'Email Address',
            'Email Verified',
            'Registration Date'
        ];
    }

    /**
     * Transform model data for export
     * This method is called for each record during export
     */
    public function transformForExport(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->format('Y-m-d H:i:s') : 'Not Verified',
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Example: Export only active users
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Example: Export with relationships
     */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Example: Export with relationship data
     */
    public function transformForExportWithProfile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->profile?->phone ?? 'N/A',
            'city' => $this->profile?->city ?? 'N/A',
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}