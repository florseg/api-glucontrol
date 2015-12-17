<?php 
require 'vendor/autoload.php';
require 'Models/User.php';
require 'Models/Glucemia.php';


function simple_encrypt($text,$salt){  
   return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
}
 
function simple_decrypt($text,$salt){  
    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $salt, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
}

$app = new \Slim\Slim();

$app->enc_key = '1234567891234567';


$app->config('databases', [
    'default' => [
        'driver'    => 'mysql',
        'host'      => 'us-cdbr-iron-east-03.cleardb.net',
        'database'  => 'heroku_68bdc0a1494ac25',
        'username'  => 'b54b6cf9bc0015',
        'password'  => '37780c33',
        'charset'   => 'utf8',
        'collation' => 'utf8_general_ci',
        'prefix'    => ''
    ]
]);


$app->add(new Zeuxisoo\Laravel\Database\Eloquent\ModelMiddleware);

$app->view(new \JsonApiView());
$app->add(new \JsonApiMiddleware());
$app->add(new \Slim\Middleware\ContentTypes());

$app->options('/(:name+)', function() use ($app) {
    $app->render(200,array('msg' => 'CODIPA'));
});

$app->get('/', function () use ($app) {
	$app->render(200,array('msg' => 'CODIPA'));
});

//Mostrar

$app->get('/usuarios', function () use ($app) {
	$db = $app->db->getConnection();
	$usuarios = $db->table('usuarios')->select('id', 'name', 'email')->get();
	$app->render(200,array('data' => $usuarios));
});


//Login 

$app->post('/login', function () use ($app) {
	$input = $app->request->getBody();

	$email = $input['email'];
	if(empty($email)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'Se requiere el Email',
        ));
	}
	$password = $input['password'];
	if(empty($password)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'Se requiere la Contraseña',
        ));
	}
	$db = $app->db->getConnection();
	$usuarios = $db->table('usuarios')->select()->where('email', $email)->first();
	if(empty($usuarios)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'El usuario no existe',
        ));
	}
	if($usuarios->password != $password){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'La password no coincide',
        ));
	}

	$token = simple_encrypt($usuarios->id, $app->enc_key);

	$app->render(200,array('token' => $token));
});

$app->get('/me', function () use ($app) {

	$token = $app->request->headers->get('auth-token');

	if(empty($token)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'Not logged1',
        ));
	}
	
	$id_user_token = simple_decrypt($token, $app->enc_key);

	$usuario = User::find($id_user_token);
	if(empty($usuario)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'Not logged2',
        ));
	}
	$app->render(200,array('data' => $usuario->toArray()));
});




//crear usuario

$app->post('/usuarios', function () use ($app) {

  $input = $app->request->getBody();
	$name = $input['name'];
	if(empty($name)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'name is required',
        ));
	}

	$password = $input['password'];
	if(empty($password)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'password is required',
        ));
	}

	$email = $input['email'];
	if(empty($email)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'email is required',
        ));
	}

	
    $usuario = new User();
    $usuario->name = $name;
    $usuario->password = $password;
    $usuario->email = $email;
    $usuario->save();
    $app->render(200,array('data' => $usuario->toArray()));
});








//Conexion con la tabla glucemia

$app->get('/glucemia', function () use ($app) {
	$db = $app->db->getConnection();
	$glucemia = $db->table('glucemia')->select('id', 'idusuarios', 'fecha', 'hora', 'medicion')->get();

	$app->render(200,array('data' => $glucemia));
});

//Crear controles
$app->post('/glucemia', function () use ($app) {

	$token = $app->request->headers->get('auth-token');
	if(empty($token)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'Not logged',
        ));
	}
	$id_user_token = simple_decrypt($token, $app->enc_key);
	$usuario = User::find($id_user_token);
	if(empty($usuario)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'Not logged',
        ));
	}


	$input = $app->request->getBody();
	$fecha = $input['fecha'];
	if(empty($fecha)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'fecha is required',
        ));
	}

	$hora = $input['hora'];
	if(empty($hora)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'hora is required',
        ));
	}

		$medicion = $input['medicion'];
	if(empty($medicion)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'medicion is required',
        ));
	}



		$idusuarios = $usuario->id;
	if(empty($idusuarios)){
		$app->render(500,array(
			'error' => TRUE,
            'msg'   => 'email is required',
        ));
	}

    $glucemia = new Glucemia();
    $glucemia->idusuarios=$usuario->id;
    $glucemia->fecha = $fecha;
    $glucemia->hora = $hora;
    $glucemia->medicion = $medicion;
    $glucemia->save();
    $app->render(200,array('data' => $glucemia->toArray()));
});




//Mostrar listado de control de glucemia 

$app->get('/miscontroles', function () use ($app) {
	
		$token = $app->request->headers->get('auth-token');
		if(empty($token)){
			$app->render(500,array(
				'error' => TRUE,
				'msg'   => 'Not logged',
			));
		}
		$id_user_token = simple_decrypt($token, $app->enc_key);
		$glucemia = Glucemia::find($id_user_token);
		if(empty($glucemia)){
			$app->render(500,array(
				'error' => TRUE,
				'msg'   => 'Not logged',
			));
		}
		
	$db = $app->db->getConnection();
	$glucemia = $db->table('glucemia')->select('id', 'idusuarios', 'fecha', 'hora', 'medicion')->where('idusuarios', $glucemia->id)->get();
	$app->render(200,array('data' => $glucemia));
});





//abajo de todo (cierra la api)
$app->run();
?>