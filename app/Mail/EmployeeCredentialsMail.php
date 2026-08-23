<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmployeeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $password,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your Project Management System account')
            ->view('emails.employee-credentials')
            ->with([
                'name' => $this->employee->full_name,
                'email' => $this->employee->email,
                'password' => $this->password,
                'loginUrl' => config('app.frontend_url'),
            ]);
    }
}
