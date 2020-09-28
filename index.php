<?php
error_reporting(E_ALL);

$web = getenv('WEB_URL');

$personas = array(
    array("Carlos Ramos", "kmario1019@gmail.com", 'm')
);

function conectar()
{
    $host = getenv('DATABASE_HOST');
    $port = getenv('DATABASE_PORT');
    $dbname = getenv('DATABASE_NAME');
    $user = getenv('DATABASE_USER');
    $password = getenv('DATABASE_PASSWORD');

    $conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password options='--client_encoding=UTF8'";
    $connection = pg_connect($conn_string);

    return $connection;
}

function listar_personas()
{
    $conn = conectar();

    $result = pg_query($conn, "SELECT p.name, f.name AS friend_name FROM personas AS p LEFT JOIN personas AS f ON f.token = p.me_toco");

    while ($row = pg_fetch_assoc($result)) {
        echo "A {$row['name']} le tocó {$row['friend_name']}<br />";
    }
}

function generar_token()
{
    $conn = conectar();
    $result = pg_query($conn, "SELECT correo FROM personas;");
    while ($row = pg_fetch_assoc($result)) {
        $codigo = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
        pg_query($conn, "UPDATE personas SET token = '$codigo' WHERE correo = '{$row['correo']}'");
    }
}

function bot()
{
    $conn = conectar();
    $result = pg_query($conn, "SELECT token, sexo FROM personas ORDER BY sexo ASC");
    while ($row = pg_fetch_assoc($result)) {
        $token_result = pg_query($conn, "SELECT token FROM personas WHERE sexo != '{$row['sexo']}' AND le_toque = '0' ORDER BY rand() LIMIT 1");
        $token_row = pg_fetch_assoc($token_result);
        $token = $token_row['token'];
        if (!empty($token)) {
            pg_query($conn, "UPDATE personas SET me_toco = '$token' WHERE token = '{$row['token']}'");
            pg_query($conn, "UPDATE personas SET le_toque = '{$row['token']}' WHERE token = '$token'");
        } else {
            $token_result = pg_query($conn, "SELECT token FROM personas WHERE sexo = '{$row['sexo']}' AND le_toque = '0' AND token != '$token' ORDER BY rand() LIMIT 1");
            $token_row = pg_fetch_assoc($token_result);
            $token = $token_row['token'];
            pg_query($conn, "UPDATE personas SET me_toco = '$token' WHERE token = '{$row['token']}'");
            pg_query($conn, "UPDATE personas SET le_toque = '{$row['token']}' WHERE token = '$token'");
        }
    }
}

function guardar_personas_bd()
{
    global $personas;
    $conn = conectar();
    $insert = [];
    foreach ($personas as $p) {
        list($nombre, $apellido) = explode(' ', $p[0]);
        $insert[] = "('$nombre', '$apellido', '{$p[1]}', '{$p[2]}')";
    }
    pg_query($conn, "INSERT INTO personas (nombre, apellido, correo, sexo) VALUES " . implode(',', $insert));
}

function send_emails()
{
    global $web;

    $conn = conectar();
    $result = pg_query($conn, "SELECT * FROM personas WHERE id = 16 AND token is null LIMIT 5");

    include './lib/PHPMailer/PHPMailerAutoload.php';

    while ($row = pg_fetch_assoc($result)) {
        $token = generateRandomString();

        $mail = new PHPMailer;

        //$mail->SMTPDebug = 2;
        $mail->CharSet = 'utf-8';
        $mail->setLanguage('es');

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('EMAIL_USERNAME');
        $mail->Password = getenv('EMAIL_PASSWORD');
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom(getenv('EMAIL_USERNAME'), 'Amigo Secreto');

        $correo = $row['mail'];
        $nombre = "{$row['name']}";
        $mail->addAddress($correo, $nombre);

        $mail->addReplyTo(getenv('EMAIL_USERNAME'), 'Comité Social SRE');
        $mail->isHTML(true);

        $cuerpo = '<p style="text-align: justify;">Hola ' . $nombre . ',<br/></p>

<p style="text-align: justify;">De acuerdo al formulario compartido por Slack, estás participando en el juego del amigo secreto de este año en Condor Labs.</p>

<p style="text-align: justify;">A continuación tendrás un enlace para acceder a la aplicación que elegirá de manera aleatoria a tu amigo y tendrás todos los datos necesarios para hacerle llegar su detalle.</p>

<p style="text-align: justify;">Si quieres actualizar lo que quisieras de regalo, puedes hacerlo también desde la aplicación.</p>

<p style="text-align: justify;"><strong>Algunas consideraciones:</strong></p>

<ul>
	<li style="text-align: justify;">Puedes dejar nota de quien eres al enviar tu regalo o si quieres seguir en el anonimato, déjale una pista para que intente adivinar quien eres. 🤫</li>
	<li style="text-align: justify;">Asegurate que dónde vayas a realizar el pedido y quien vaya a enviarlo (si es mensajero), tenga en cuenta todas las precauciones y cuidados contra el COVID-19. 😷</li>
</ul>

<p style="text-align: justify;font-size:15px;"><strong><span style="color: rgb(226, 80, 65);">Fecha de celebración:</span></strong> Viernes 2 de Octubre 🥳</p>

<p style="text-align: center;"><a href="' . $web . '/' . $token . '" style="display: inline-block;padding:15px 25px;background:#2196f3;color:#fff;text-shadow:1px 1px 1px rgba(0,0,0,0.12);text-decoration: none;box-shadow: 0 2px 5px 0 rgba(0,0,0,0.16),0 2px 10px 0 rgba(0,0,0,0.12);border-radius:4px;">CONTINUAR A LA APP</a></p>

<p style="text-align: justify;">
	<br>
</p>

<p style="text-align: center;">&iexcl;Que sigas teniendo un excelente d&iacute;a!</p>

<p style="text-align: center;">
	<br>
</p>

<p style="text-align: center;"><img src="https://i.imgur.com/A8SExjo.png" class="fr-dii fr-draggable"></p>
';
        $body = '<div style="width: 100%;background: #fff;font-family:Helvetica,Arial,sans-serif;text-align: center;">
                <div style="width:100%;margin:auto;box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 20px;border-radius:5px;display: inline-block;text-align: left">
                    <div style="background:#fdcd07;margin:0;padding: 10px 20px;color:#231f20;border: 1px solid #e2b600;font-size:25px;border-radius: 5px 5px 0 0;"><strong>Amor y Amistad en Condor Labs 🦅</strong></div>
                    <div style="padding: 20px;color:#231f20;border: solid 1px #ccc;border-top:0;font-size:14px;  border-radius: 0 0 5px 5px;margin: 0">' . $cuerpo . '</div>
                </div>
                <div style="text-align: center;padding-top: 30px;font-size:12px;color:#ccc">Amigo Secreto Condor Labs 2020 made by Carlos Ramos</div>
            </div>';

        $mail->Subject = "👀 ¡Conoce a tu Amigo Secreto!";
        $mail->Body = $body;
        $mail->AltBody = 'Hey, abreme!';

        // Enviar email
        if (!$mail->send()) {
            echo "$correo...ERROR: {$mail->ErrorInfo}<br>";
        } else {
            pg_query($conn, "UPDATE personas SET token = '$token' WHERE id = {$row['id']}");
            echo "$correo...OK<br>";
        }
    }
}

$token = isset($_GET['token']) ? $_GET['token'] : null;
$conn = conectar();

if (!empty($token)) {
    $query = pg_query($conn, "SELECT * FROM personas WHERE token = '$token'");
    $persona = pg_fetch_assoc($query);

    if (!empty($persona['me_toco'])) {
        $query = pg_query($conn, "SELECT * FROM personas WHERE token = '{$persona['me_toco']}';");
        $me_toca = pg_fetch_assoc($query);
    } else {
        $query2 = pg_query($conn, "SELECT * FROM personas WHERE sex != '{$persona['sex']}' AND token is not null AND le_toque is null ORDER BY random() LIMIT 1");
        $persona2 = pg_fetch_assoc($query2);
        $token = $persona2['token'];
        // Si no encuentra pareja de distinto sexo se busca otra del mismo
        if (!empty($token)) {
            pg_query($conn, "UPDATE personas SET me_toco = '$token' WHERE token = '{$persona['token']}'");
            pg_query($conn, "UPDATE personas SET le_toque = '{$persona['token']}' WHERE token = '$token'");
        } else {
            $query2 = pg_query($conn, "SELECT * FROM personas WHERE sex = '{$persona['sex']}' AND token is not null AND le_toque is null AND token != '{$persona['token']}' ORDER BY random() LIMIT 1");
            $persona2 = pg_fetch_assoc($query2);
            $token = $persona2['token'];
            if (!empty($token)) {
                pg_query($conn, "UPDATE personas SET me_toco = '$token' WHERE token = '{$persona['token']}'");
                pg_query($conn, "UPDATE personas SET le_toque = '{$persona['token']}' WHERE token = '$token'");
            }
        }
        $me_toca = $persona2;
    }
}

function generateRandomString($length = 5)
{
    return substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}

// Ajax
if (isset($_POST['gustos'])) {
    pg_prepare($conn, 'update_personas', "UPDATE personas SET likes = $1 WHERE token = '$token'");
    if (pg_execute($conn, 'update_personas', [$_POST['gustos']]))
        die("Cambios guardados con éxito.");
    else
        die("Error");
}

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'list':
            listar_personas();
            exit;
        case 'send_emails':
            send_emails();
            exit;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Amigo Secreto | Condor Labs</title>
    <link rel="shortcut icon" href="<?= $web ?>/img/2.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="css.css">
    <link href="http://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body class="red">
    <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

    <div class="container">
        <div class="row" style="margin-top: 5%">
            <div class="col offset-l3 l6 offset-m2 m8 s12">
                <div class="card">
                    <div class="card-image">
                        <img src="<?= $web ?>/img/background.jpg" />
                    </div>
                    <?php if (!empty($persona['name'])) { ?>
                        <div class="card-content center-align">
                            <p>
                                <h4 class="red-text darken-2"><?= "Hola {$persona['name']}" ?></h4>
                            </p>
                            <span class="card-title grey-text text-darken center-align-4">Descubre quién es tu amigo secreto...</span>
                        </div>
                        <div class="card-action center-align">
                            <button type="button" class="waves-effect activator waves-light btn pulse" onclick="load_amigo()">Continuar</button>
                        </div>
                        <div class="card-reveal">
                            <span class="card-title grey-text text-darken-4 center-align">TU AMIGO SECRETO ES:</span>
                            <?php if (empty($persona['me_toco'])) { ?>
                                <div class="center-align" style="margin-top: 100px;" id="loading">
                                    <div class="preloader-wrapper big active">
                                        <div class="spinner-layer spinner-blue">
                                            <div class="circle-clipper left">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="gap-patch">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="circle-clipper right">
                                                <div class="circle"></div>
                                            </div>
                                        </div>
                                        <div class="spinner-layer spinner-red">
                                            <div class="circle-clipper left">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="gap-patch">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="circle-clipper right">
                                                <div class="circle"></div>
                                            </div>
                                        </div>
                                        <div class="spinner-layer spinner-yellow">
                                            <div class="circle-clipper left">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="gap-patch">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="circle-clipper right">
                                                <div class="circle"></div>
                                            </div>
                                        </div>
                                        <div class="spinner-layer spinner-green">
                                            <div class="circle-clipper left">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="gap-patch">
                                                <div class="circle"></div>
                                            </div>
                                            <div class="circle-clipper right">
                                                <div class="circle"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="rainbow rainbow_text_animated">Espera un momento...</p>
                                </div>
                            <?php } ?>
                            <div id="amigo" <?php if (empty($persona['me_toco'])) echo ' style="display:none"'; ?>>
                                <p>
                                    <?php if (!empty($me_toca['id'])) { ?>
                                        <div class="center-align">
                                            <img src="<?= $me_toca['avatar'] ?>" class="circle responsive-img" width="200" height="200" />
                                        </div>
                                        <h3 class="red-text darken-2 center-align"><?= $me_toca['name'] ?></h3>
                                        <ul class="collection">
                                            <li class="collection-item">
                                                <strong>Teléfono:</strong> <?= $me_toca['cellphone'] ?>
                                            </li>
                                            <li class="collection-item"><strong>Dirección:</strong> <?= $me_toca['direction'] ?></li>
                                            <?php if ($me_toca['reference']) { ?>
                                                <li class="collection-item"><strong>Referencia:</strong> <?= $me_toca['reference'] ?></li>
                                            <?php } ?>
                                            <li class="collection-item"><strong>Gustos:</strong> <?= $me_toca['likes'] ?: "{$me_toca['name']} no ha compartido sus gustos todavia, vuelve luego a ver si ya lo ha hecho.";  ?></li>
                                        </ul>
                                    <?php } else { ?>
                                        <div class="center-align">
                                            <img src="<?= $persona['avatar'] ?>" class="circle responsive-img" width="200" height="200" />
                                        </div>
                                        <p>No se ha encontrado tu amigo secreto, contacta a <strong>Carlos Ramos</strong> para que te solucione 💥</p>
                                    <?php } ?>
                                    <br /><br />
                                    <form>
                                        <div class="input-field">
                                            <textarea id="gustos" class="materialize-textarea"><?= $persona['likes'] ?></textarea>
                                            <label for="icon_prefix2">Actualiza tus gustos</label>
                                        </div>
                                        <input type="hidden" id="token" value="<?= $persona['token'] ?>" />
                                    </form>
                                </p>
                                <div class="card-action">
                                    <a class="waves-effect waves-light btn" onclick="guardar_gustos()"><i class="material-icons">save</i></a>
                                    <a class="waves-effect waves-light modal-action modal-close btn red right" href="<?= $web ?>"><i class="material-icons">close</i></a>
                                </div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="card-content center-align">
                            <span class="card-title grey-text text-darken center-align-4">DESCUBRE TU AMIGO SECRETO</span>
                            <p>Para acceder a la aplicación, ingresa desde el enlace enviado a tu correo.</p>
                            <br />
                            <p class="grey-text darken-2">Amor y Amistad en Condor Labs 2020</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        function load_amigo() {
            setTimeout(function() {
                $('#loading').slideUp();
                $('#amigo').slideDown();
            }, 4000);
        }

        function guardar_gustos() {
            var gustos = $("#gustos").val();
            if (gustos.length == 0)
                return;
            $.post('<?= $web ?>/?token=' + $("#token").val(), {
                gustos: gustos
            }, function(r) {
                M.toast({
                    html: r
                })
            })
        }
    </script>
</body>

</html>