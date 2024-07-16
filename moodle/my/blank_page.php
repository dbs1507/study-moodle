<?php

require_once('../../config.php');

$PAGE->set_context(get_system_context());
$PAGE->set_pagelayout('admin');
$PAGE->set_title("Central de ajuda");
$url = new moodle_url('/blocks/adaptadev/blank_page.php');

$PAGE->set_url($url);

echo $OUTPUT->header();

echo "<div class='central-container' style='box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);background-color: #fff;padding: 3rem;margin-bottom: 2rem;border-radius: 10px;'>";
echo "<h6>Confira abaixo as principais dúvidas sobre acesso a plataforma.<h6>";
echo "<br>";
echo "<h2>Como redefinir minha senha?</h2>";
echo "<br>";
echo "<ul>";
echo "<li>Na pagina de login, clique em 'Esqueceu sua senha?'.</li>";
echo "<li>Na próxima página, leia as orientações e escolha se quer redefinir pelo nome de usuário ou o email usado na plataforma.</li>";
echo "<li>Se o email ou o nome de usuário estiver correto, um email será enviado a você.</li>";
echo "<li>O email enviado contém as instruções para confirmar e completar a alteração de senha.</li>";
echo "<li>O link enviado possui validade de 30 minutos para o acesso, caso o tempo se exeda, deve-se repetir o processo do início.</li>";
echo "</ul>";
echo "<a class='btn btn-primary ' href='https://studydev.vezos.com.br/login/index.php'>Voltar ao Login</a>";
echo "</div>";
echo $OUTPUT->footer();
