<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $content;
    public $data;
    public $view;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param string|array $content
     * @param array $data
     * @param string|null $view
     */
    public function __construct(string $subject, $content, array $data = [], string $view = null)
    {
        $this->subject = $subject;
        $this->content = $content;
        $this->data = $data;
        $this->view = $view;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->subject($this->subject);

        if ($this->view) {
            return $email->view($this->view, [
                'content' => $this->content,
                'data' => $this->data,
                'subject' => $this->subject
            ]);
        }

        if (is_string($this->content)) {
            return $email->text('emails.generic_text', [
                'content' => $this->content,
                'data' => $this->data
            ]);
        }

        return $email->view('emails.generic', [
            'content' => $this->content,
            'data' => $this->data,
            'subject' => $this->subject
        ]);
    }
}
