<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//require_once (APPPATH . 'vendor/autoload.php');

class Email_model extends CI_Model {
	public function __construct()
	{
		parent::__construct();
		require APPPATH.'libraries/phpmailer/src/Exception.php';
        require APPPATH.'libraries/phpmailer/src/PHPMailer.php';
        require APPPATH.'libraries/phpmailer/src/SMTP.php';
	}

	public function send($from, $to, $subject, $message)
	{
        $response = false;
        $mail = new PHPMailer();
   
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host         = 'in-v3.mailjet.com'; //sesuaikan sesuai nama domain hosting/server yang digunakan
        $mail->SMTPAuth     = true;
        $mail->Username     = '4474201765c204e69583260ef2ef8b8b'; // user email
        $mail->Password     = 'fa06dce923ed6c7d727071275fafe344'; // password email
        $mail->SMTPSecure   = 'ssl';
        $mail->Port         = 465;

        $mail->setFrom($from, ''); // user email
        $mail->addAddress($to);
        $mail->Subject = $subject; //subject email
        $mail->isHTML(true);

        $mailContent = $message; // isi email
        $mail->Body = $mailContent;

        if(!$mail->send()){
            return false;
        }else{
            return true;
        }
        
	}

}

/* End of file Blog.php */
/* Location: ./application/controllers/Blog.php */

