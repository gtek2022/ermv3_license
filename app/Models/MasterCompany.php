<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterCompany extends Model
{
    use SoftDeletes;

    protected $table = 'master_companies';

    protected $fillable = [
        'code', 'name', 'email', 'phone',
        'address', 'city', 'country', 'website',
        'logo_path', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    public function licenseCompanies()
    {
        return $this->hasMany(LicenseCompany::class, 'company_id');
    }

    public function activeLicenses()
    {
        return $this->licenseCompanies()->where('status', 'active');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
