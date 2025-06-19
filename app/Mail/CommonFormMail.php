<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommonFormMail extends Mailable
{
    public $formData;
    public $emailType;

    public function __construct(array $formData, string $emailType = 'form')
    {
        $this->formData = $formData;
        $this->emailType = $emailType;
    }

    public function build()
    {
        switch ($this->emailType) {
            case 'forgot_password':
                $subject = 'Reset Your Password';
                $content = $this->buildForgotPasswordHtml();
                break;

            case 'contact_form':
                $subject = $this->formData['subject'] ?? 'New Contact Form Submission';
                $content = $this->buildHtmlContent();
                break;

            default:
                $subject = $this->formData['subject'] ?? 'New Submission';
                $content = $this->buildHtmlContent();
        }

        return $this->subject($subject)->html($content);
    }

    private function buildHtmlContent()
    {
        $html = "<h2>Form Submission</h2><ul>";
        foreach ($this->formData as $key => $value) {
            $html .= "<li><strong>" . ucfirst($key) . ":</strong> " . e($value) . "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    private function buildForgotPasswordHtml()
    {
        $resetLink = $this->formData['reset_link'] ?? '#';
        return "
            <h2>Password Reset Request</h2>
            <p>Hi {$this->formData['name']},</p>
            <p>Click the button below to reset your password:</p>
            <p><a href=\"{$resetLink}\" style=\"display:inline-block;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:5px;\">Reset Password</a></p>
            <p>If you did not request a password reset, please ignore this email.</p>
        ";
    }
}