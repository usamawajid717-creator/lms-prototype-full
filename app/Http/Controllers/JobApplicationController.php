<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    /**
     * Show the job application form.
     */
    public function create()
    {
        $locations = ['Lahore', 'Islamabad', 'Karachi', 'Peshawar', 'Sargodha'];

        return view('job.form', compact('locations'));
    }

    /**
     * Handle form submission and save to MySQL (Library database).
     */
    public function store(Request $request)
    {
        // Validate all fields
        $validated = $request->validate([
            'User_Name'          => 'required|string|max:100',
            'User_Contact_Number'=> 'required|string|max:30',
            'Job_ID'           => 'required|string|max:50|unique:Job_Application,Job_ID',
            'Job_Title'        => 'required|string|max:50',
            'Company_Name'     => 'required|string|max:50',
            'Job_Location'     => 'required|in:Lahore,Islamabad,Karachi,Peshawar,Sargodha',
            'Applicant_Gender' => 'required|in:Male,Female',
            'Exp_Salary'       => 'required|string|max:50',
            'Terminal_Degree'  => 'required|string|max:50',
        ], [
            'Job_ID.unique'    => 'This Job ID already exists. Please use a different ID.',
        ]);

        // Save to database
        JobApplication::create($validated);

        // Redirect to success page
        return redirect()->route('job.success')->with('job_id', $validated['Job_ID']);
    }

    /**
     * Store a job application submitted through the JSON API.
     */
    public function storeApi(Request $request)
    {
        $validated = $request->validate([
            'User_Name'           => 'required|string|max:100',
            'User_Contact_Number' => 'required|string|max:30',
            'Job_ID'              => 'required|string|max:50|unique:Job_Application,Job_ID',
            'Job_Title'           => 'required|string|max:50',
            'Company_Name'        => 'required|string|max:50',
            'Job_Location'        => 'required|in:Lahore,Islamabad,Karachi,Peshawar,Sargodha',
            'Applicant_Gender'    => 'required|in:Male,Female',
            'Exp_Salary'          => 'required|string|max:50',
            'Terminal_Degree'     => 'required|string|max:50',
        ]);

        $application = JobApplication::create($validated);

        return response()->json([
            'message' => 'Job application submitted successfully.',
            'data' => $application,
        ], 201);
    }

    /**
     * Show success page after submission.
     */
    public function success()
    {
        return view('job.success');
    }
}
