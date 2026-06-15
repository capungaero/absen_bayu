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
   
        // SMTP configuration — kredensial dari env / application/config/email.local.php (gitignored)
        $smtp = $this->_smtp_config();
        $mail->isSMTP();
        $mail->Host         = $smtp['host'];
        $mail->SMTPAuth     = true;
        $mail->Username     = $smtp['user'];
        $mail->Password     = $smtp['pass'];
        $mail->SMTPSecure   = $smtp['secure'];
        $mail->Port         = $smtp['port'];

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

	private function _smtp_config()
	{
		$cfg = [
			'host'   => getenv('ABSEN_SMTP_HOST') ?: 'in-v3.mailjet.com',
			'user'   => getenv('ABSEN_SMTP_USER') ?: '',
			'pass'   => getenv('ABSEN_SMTP_PASS') ?: '',
			'secure' => getenv('ABSEN_SMTP_SECURE') ?: 'ssl',
			'port'   => (int)(getenv('ABSEN_SMTP_PORT') ?: 465),
		];
		$local = APPPATH.'config/email.local.php';
		if (is_file($local)) {
			$override = require $local;
			if (is_array($override)) {
				$cfg = array_merge($cfg, $override);
			}
		}
		return $cfg;
	}

}

/* End of file Blog.php */
/* Location: ./application/controllers/Blog.php */

