<?php
error_reporting(E_ALL);

$web = $_ENV['WEB_URL'];

$personas = array(
    array("Carlos Ramos", "kmario1019@gmail.com", 'm')
);

function conectar()
{
    $host = $_ENV['DATABASE_HOST'];
    $port = $_ENV['DATABASE_PORT'];
    $dbname = $_ENV['DATABASE_NAME'];
    $user = $_ENV['DATABASE_USER'];
    $password = $_ENV['DATABASE_PASSWORD'];

    $conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password options='--client_encoding=UTF8 --application_name=AmigoSecretoApp'";
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
    $conn = conectar();
    $result = pg_query($conn, "SELECT * FROM personas WHERE token = 'CBON2'");

    include '../../PHPMailer/PHPMailerAutoload.php';

    while ($row = pg_fetch_assoc($result)) {
        $mail = new PHPMailer;

        $mail->SMTPDebug = 2;
        $mail->CharSet = 'utf-8';
        $mail->setLanguage('es');

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['EMAIL_USERNAME'];
        $mail->Password = $_ENV['EMAIL_PASSWORD'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom($_ENV['EMAIL_USERNAME'], 'Amigo Secreto');

        $correo = $row['correo'];
        $nombre = "{$row['nombre']} {$row['apellido']}";
        $mail->addAddress($correo, $nombre);

        $mail->addReplyTo($_ENV['EMAIL_USERNAME'], 'Comité Social SRE');
        $mail->isHTML(true);

        $cuerpo = '<p style="text-align: justify;">Buenas tardes,</p>

<p style="text-align: justify;">Cordial saludo y un gran abrazo a toda la familia S.R.E.</p>

<p style="text-align: justify;">
	<br>
</p>

<p style="text-align: justify;">Con base a la encuesta que se realiz&oacute; anteriormente se ha determinado que ser&aacute;n tres d&iacute;as para llevar a cabo el juego del amigo secreto, a continuaci&oacute;n se relaciona los d&iacute;as correspondientes para llevar a cabo la actividad, ademas que se muestran los resultados de manera gr&aacute;fica:</p>

<ul>
	<li style="text-align: justify;"><strong>16 de Septiembre</strong> Entrega del dulce ? (golosinas, chocolates, etc.)</li>
	<li style="text-align: justify;"><strong>23 de Septiembre</strong> Entrega del salado ? (mekatos, man&iacute;es, etc.)</li>
	<li style="text-align: justify;"><strong>29 de Septiembre</strong> Entrega del regalo ? (depende si la persona comparte sus gustos)</li>
</ul>

<p style="text-align: justify;">El precio de los consumibles queda en consideraci&oacute;n tuya, para el regalo, por votaci&oacute;n de la mayor&iacute;a, ser&aacute; de entre <strong>$10.000 y $20.000</strong>.</p>

<p style="text-align: center;"><strong>ESTAD&Iacute;STICAS</strong></p>

<p style="text-align: center;"><img class="fr-dib fr-draggable" src="http://i.imgur.com/9lEA177.png"></p>

<p style="text-align: center;"><img class="fr-dib fr-draggable" src="http://i.imgur.com/xqaYuIm.png"></p>

<p style="text-align: justify;">Al momento de entregar los obsequios haz lo posible para que tu amigo secreto no sepa que l@ tienes, ing&eacute;niatelas! ?</p>

<p style="text-align: justify;">Ahora compartiremos contigo la aplicaci&oacute;n que asignar&aacute; a tu amigo secreto de manera aleatoria para esta &eacute;poca de amor y amistad. ?</p>

<p style="text-align: center;"><a href="http://sre.postland.com.mx/amigo_secreto/' . $row['token'] . '" style="display: inline-block;padding:15px 25px;background:#2196f3;color:#fff;text-shadow:1px 1px 1px rgba(0,0,0,0.12);text-decoration: none;box-shadow: 0 2px 5px 0 rgba(0,0,0,0.16),0 2px 10px 0 rgba(0,0,0,0.12);border-radius:4px;">CLICK AQUI</a></p>

<p style="text-align: justify;">Despu&eacute;s de saber quien es tu amigo secreto, tendr&aacute;s la opci&oacute;n de compartir tus gustos para que la persona a quien le toques tenga una idea de qu&eacute; regalarte.</p>

<p style="text-align: justify;">
	<br>
</p>

<p style="text-align: justify;"><strong><span style="color: rgb(226, 80, 65);">Nota:</span></strong> El d&iacute;a de la entrega del regalo ser&aacute; tambi&eacute;n el d&iacute;a de la celebraci&oacute;n de los cumplea&ntilde;os del mes de Septiembre, ademas recordamos que para el d&iacute;a <strong>16 del presente mes</strong> se estar&aacute; haciendo la recolecci&oacute;n de la cuota DE $5.000 pesos de los cumplea&ntilde;os; Agradecemos ser puntual y responsable con la entrega de la misma.&nbsp;</p>

<blockquote>

	<p style="text-align: justify;"><span style="color: rgb(226, 80, 65);"><strong>Las personas que no pagaron la cuota de la segunda quincena de agosto deber&aacute;n pagar las dos pendientes en esta pr&oacute;xima, es decir, $10.000.</strong></span></p>
</blockquote>

<p style="text-align: center;">&iexcl;Que tengas un buen d&iacute;a!</p>

<p style="text-align: center;">
	<br>
</p>

<p style="text-align: center;">Atentamente</p>

<p style="text-align: center;"><img src="https://ci4.googleusercontent.com/proxy/Bxzva7m_bt1gkPY9y-rzSeDN7its5eTYBqKDq20zBoyj8_6oFWvdzFla8VrnApSSILsJBQ=s0-d-e1-ft#http://i.imgur.com/UCjO0vR.png" class="fr-dii fr-draggable"></p>
';
        $body = '<div style="width: 100%;background: #fff;font-family:Helvetica,Arial,sans-serif;text-align: center;">
                <div style="width:100%;margin:auto;box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 20px;border-radius:5px;display: inline-block;text-align: left">
                    <div style="background:#149DD7;margin:0;padding: 10px 20px;color:#fff;border: 1px solid #107CA9;font-size:25px;border-radius: 5px 5px 0 0;"><strong>Comité Social S.R.E.</strong></div>
                    <div style="padding: 20px;color:#555;border: solid 1px #ccc;border-top:0;font-size:14px;  border-radius: 0 0 5px 5px;margin: 0">' . $cuerpo . '</div>
                </div>
                <div style="text-align: center;padding-top: 30px;font-size:12px;color:#ccc">Comité Social de S.R.E. 2016-2 conformado por: Carlos Ramos, Diana Hernández, Yanine Garcés y Yuranis Lobo<br><strong><span style="color:#2969b0">#YoAmoSRE</span></strong></div>
            </div>';

        $mail->Subject = "¡Conoce tu Amigo Secreto!";
        $mail->Body = $body;
        $mail->AltBody = 'Hey, abreme!';

        // Enviar email
        if (!$mail->send()) {
            echo "$correo...ERROR: {$mail->ErrorInfo}<br>";
        } else {
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
        $query2 = pg_query($conn, "SELECT * FROM personas WHERE sexo != '{$persona['sexo']}' AND le_toque = '' ORDER BY rand() LIMIT 1");
        $persona2 = pg_fetch_assoc($query2);
        $token = $persona2['token'];
        // Si no encuentra pareja de distinto sexo se busca otra del mismo
        if (!empty($token)) {
            pg_query($conn, "UPDATE personas SET me_toco = '$token' WHERE token = '{$persona['token']}'");
            pg_query($conn, "UPDATE personas SET le_toque = '{$persona['token']}' WHERE token = '$token'");
        } else {
            $query2 = pg_query($conn, "SELECT * FROM personas WHERE sexo = '{$persona['sexo']}' AND le_toque = '' AND token != '$token' ORDER BY rand() LIMIT 1");
            $persona2 = pg_fetch_assoc($query2);
            $token = $persona2['token'];
            pg_query($conn, "UPDATE personas SET me_toco = '$token' WHERE token = '{$persona['token']}'");
            pg_query($conn, "UPDATE personas SET le_toque = '{$persona['token']}' WHERE token = '$token'");
        }
        $me_toca = $persona2;
    }
}
//send_emails();
// Ajax
if (isset($_POST['gustos'])) {
    if (pg_query($conn, "UPDATE personas SET gustos = '" . htmlspecialchars($_POST['gustos']) . "' WHERE token = '$token'"))
        die("Cambios guardados con éxito.");
    else
        die("Error");
}

if (isset($_GET['action'])) {
    switch ($GET['action']) {
        case 'list':
            listar_personas();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.97.7/css/materialize.min.css">
    <link href="http://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body class="red">
    <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.97.7/js/materialize.min.js"></script>

    <div class="container">
        <div class="row" style="margin-top: 5%">
            <div class="col offset-l3 l6 offset-m2 m8 s12">
                <div class="card">
                    <div class="card-image">
                        <img src="<?= $web ?>/img/background.jpg" />
                    </div>
                    <?php if (!empty($persona['nombre'])) { ?>
                        <div class="card-content center-align">
                            <span class="card-title grey-text text-darken center-align-4">JUGUEMOS AL AMIGO SECRETO</span>
                            <p>
                                <h3 class="red-text darken-2"><?= "¿Eres {$persona['nombre']} {$persona['apellido']}?" ?></h3>
                            </p>
                        </div>
                        <div class="card-action">
                            <a class="waves-effect activator waves-light btn" href="#" onclick="load_amigo(); return false;"><i class="material-icons right ">thumb_up</i>Continuar</a>
                            <a href="<?= $web ?>" class="waves-effect waves-light right btn red"><i class="material-icons">close</i></a>
                        </div>
                        <div class="card-reveal">
                            <span class="card-title grey-text text-darken-4">TU AMIGO SECRETO ES...<i class="material-icons right">close</i></span>
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
                                    <p>Espera un momento...</p>
                                </div>
                            <?php } ?>
                            <div id="amigo" <?php if (empty($persona['me_toco'])) echo ' style="display:none"'; ?>>
                                <p>
                                    <div class="center-align">
                                        <?php if (!empty($me_toca['fbid'])) { ?>
                                            <img src="https://graph.facebook.com/<?= $me_toca['fbid'] ?>/picture?width=200&height=200" style="max-height:200px" class="circle responsive-img" />
                                        <?php } else { ?>
                                            <img src="<?= $web ?>/img/4.jpg" class="circle responsive-img" width="200" height="200" />
                                        <?php } ?>
                                    </div>
                                    <h3 class="red-text darken-2 center-align"><?= "{$me_toca['nombre']} {$me_toca['apellido']}" ?></h3>
                                    <h5>Ubicación:</h5>
                                    Su puesto de trabajo está en <strong><?= $me_toca['lab'] ?></strong>.
                                    <h5>Gustos:</h5>
                                    <?php
                                    if (!empty($me_toca['gustos'])) {
                                        echo $me_toca['gustos'];
                                    } else {
                                        echo "{$me_toca['nombre']} no ha compartido sus gustos todavia, vuelve luego a ver si ya lo ha hecho.";
                                    }
                                    ?>
                                    <br /><br />
                                    <form>
                                        <div class="input-field">
                                            <textarea id="gustos" class="materialize-textarea"><?= $persona['gustos'] ?></textarea>
                                            <label for="icon_prefix2">¿Quieres compartir tus gustos?</label>
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
                            <span class="card-title grey-text text-darken center-align-4">MI AMIGO SECRETO</span>
                            <p>Para acceder a la aplicación, ingresa desde el enlace enviado en tu correo.</p>
                            <br />
                            <p class="grey-text darken-2">S.R.E Tecnológico Comfenalco 2017-2</p>
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
                Materialize.toast(r, 4000, 'rounded')
            })
        }
    </script>
</body>

</html>