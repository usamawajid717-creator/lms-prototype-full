<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    // Match the exact table name from the assignment
    protected $table = 'Job_Application';

    // Primary key is Job_ID (string, not auto-increment)
    protected $primaryKey = 'Job_ID';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'User_Name',
        'User_Contact_Number',
        'Job_ID',
        'Job_Title',
        'Company_Name',
        'Job_Location',
        'Applicant_Gender',
        'Exp_Salary',
        'Terminal_Degree',
    ];
}
