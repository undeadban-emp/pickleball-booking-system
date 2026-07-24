<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Email;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function index()
    {
        $emails = Email::latest('id')->paginate(20);

        return view('admin.settings.emails', ['emails' => $emails]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'in:pending,sent,failed'],
        ]);

        Email::create($data);

        return back()->with('status', 'Email log created.');
    }

    public function update(Request $request, Email $email)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'in:pending,sent,failed'],
        ]);

        $email->update($data);

        return back()->with('status', 'Email log updated.');
    }

    public function destroy(Email $email)
    {
        $email->delete();

        return back()->with('status', 'Email log removed.');
    }
}
