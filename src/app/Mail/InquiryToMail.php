<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class InquiryToMail extends Mailable
{
    use Queueable, SerializesModels;

    // Bladeで用いるため宣言が必要
    public $name;
    public $email;
    public $contents;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    // $contentsにはInquiryControllerのsendemail()のsend()の引数が渡される
    public function __construct(Array $contents)
    {
        $this->contents = $contents;
        $this->name = $contents['name'];
        $this->email = $contents['email'];
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        // from: <email>名前となる
        $from = new Address(config('mail.from.address'), config('app.name'));
        if($this->name){
            $subject = $this->name.'様お問合せありがとうございます';
        }else{
            $subject = 'お問合せありがとうございます';
        }
        return new Envelope(
            from: $from,
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            // テキストベースに変更
            // view: 'view.name',
            text: 'inquiry.toInquiry'
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
