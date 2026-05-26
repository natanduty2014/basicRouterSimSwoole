<?php

namespace Functions\api;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class phpmailerSend
{
    public static function enviarEmail($dados)
    {
        $mailer = new PHPMailer(true);

        try {
            // ConfiguraÃ§Ãµes do servidor de e-mail
            $mailer->isSMTP();
            $mailer->Host = 'mail.econmissaoflorida.com'; // EndereÃ§o do servidor SMTP
            $mailer->SMTPAuth = true;
            $mailer->Username = 'noreply@econmissaoflorida.com'; // UsuÃ¡rio do SMTP
            $mailer->Password = 'QkM3%%%+sXG?'; // Senha do SMTP
            $mailer->SMTPSecure = 'ssl';
            $mailer->Port = 465; // Porta TCP para conexÃ£o
            $mailer->CharSet = 'UTF-8';
            // Debug desabilitado em produção (DEBUG_OFF = 0)
            $mailer->SMTPDebug = SMTP::DEBUG_OFF;
            $mailer->Timeout = 10; // Timeout de 10 segundos para não travar
            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            // Remetente
            $mailer->setFrom('noreply@econmissaoflorida.com', 'EconmissÃ£o Florida');

            // DestinatÃ¡rio
            $mailer->addAddress('contato@econmissaoflorida.com', 'EconmissÃ£o Florida');

            //replay
            $mailer->addReplyTo($dados['email'], $dados['nome']);

            // Contedo do e-mail
            $mailer->isHTML(true);
            $mailer->Subject = $dados['title'];

            // Corpo do e-mail em HTML
            // Corpo do e-mail em HTML
            $bodyContent = '';
            if (!empty($dados['nome'])) {
                $bodyContent .= '<p><strong>Nome:</strong> ' . htmlspecialchars($dados['nome']) . '</p>';
            }
            if (!empty($dados['contato'])) {
                $bodyContent .= '<p><strong>Telefone:</strong> ' . htmlspecialchars($dados['contato']) . '</p>';
            }
            if (!empty($dados['email'])) {
                $bodyContent .= '<p><strong>Email:</strong> ' . htmlspecialchars($dados['email']) . '</p>';
            }

            // Allow raw HTML in msg if needed, or stick to htmlspecialchars if strict. 
            // Refacil sends HTML "Nova senha: ... <br>". 
            // So we should probably NOT htmlspecialchars the msg if we want HTML.
            // But for security, maybe we should. 
            // Refacil's enviarMensagem receives encoded message sometimes, but basically it sends HTML.
            // Let's assume msg can be HTML for recovery.
            $bodyContent .= '<div>' . ($dados['msg'] ?? '') . '</div>';

            $bodyHTML = '
                <!DOCTYPE html>
                <html lang="pt-br">
                <head>
                    <meta charset="UTF-8">
                    <title>' . htmlspecialchars($dados['title'] ?? 'Refacil') . '</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6em;
                            background-color: #f7f7f7;
                            margin: 0;
                            padding: 0;
                        }
                        h2 {
                            color: #2c3e50;
                        }
                        p {
                            color: #333;
                        }
                        .container {
                            max-width: 600px;
                            margin: 0 auto;
                            padding: 20px;
                            background-color: #fff;
                            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                            border-radius: 5px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2>' . htmlspecialchars($dados['title'] ?? 'Mensagem') . '</h2>
                        ' . $bodyContent . '
                    </div>
                </body>
                </html>';

            $mailer->Body = $bodyHTML;

            // Enviar o e-mail
            $mailer->send();
            return true;
        } catch (Exception $e) {
            var_dump("Erro ao enviar e-mail: {$e->getMessage()}");
            return false;
        }
    }
}

// // Exemplo de uso
// $dados = [
//     'title' => 'Assunto do E-mail',
//     'nome' => 'Nome do Remetente',
//     'contato' => '123456789',
//     'email' => 'remetente@exemplo.com',
//     'msg' => 'Esta Ã© a mensagem do e-mail.'
// ];
