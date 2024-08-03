<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController {

    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            
			$auth = new Usuario($_POST);
			
            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                // echo 'El usuario proporcionó correo y contraseña';
                //Comprobar que exista el usuario
                $usuario = Usuario::buscarPorCampo('email', $auth->email);

                if($usuario) {
                    //Verificar la contraseña
                    if($usuario->comprobarContrasenaAndVerificado($auth->password)) {

                        //Autenticar usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . ' ' . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        //debuguear($_SESSION);

                        //Redireccionamiento
                        if($usuario->admin == 1) {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cliente');
                        }

                    }

                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }

            }
			
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login', [
            'alertas' => $alertas
        ]);
    }

    public static function logout() {
        echo 'Desde logout';
    }

    public static function olvide(Router $router) {
       
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            if(empty($alertas)) {

                $usuario = Usuario::buscarPorCampo('email', $auth->email);

                if($usuario && $usuario->confirmado == 1) {
                    //debuguear('Si existe y está confirmado');
                    $usuario->crearToken();
                    $usuario->guardar();

                    //Enviar el email
                    $email = new Email(
                        $usuario->email, 
                        $usuario->nombre, 
                        $usuario->token
                    );

                    $email->enviarInstrucciones();
                    
                    Usuario::SetAlerta('exito', 'Revisa tu correo');
                    
                } else {
                    //debuguear('No existe o no está confirmado');
                    Usuario::setAlerta('error', 'El usuario no existe o no está confirmado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);
    }

    public static function recuperar(Router $router) {
        
        $alertas = [];

        $error = false;

        $token = s($_GET['token']);

        // Buscar usuario por su token
        $usuario = Usuario::buscarPorCampo('token', $token);

        // debuguear($usuario);

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no válido');
            $error = true;
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // Leer el nuevo password y guardarlo
            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();

            if(empty($alertas)) {
                $usuario->password = null;

                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado) {
                    header('Location: /');
                }
            }

        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/recuperar-password', [
            'alertas' => $alertas,
            'error' => $error
        ]);

    }

    public static function crear(Router $router) {
        
        $usuario = new Usuario;

        //Alertas vacías
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();
            
            //Revisar que alertas esté vacío
            if(empty($alertas)) {

                //Verificar que el usuario no esté registrado
                $resultado = $usuario->existeUsuario();

                if ($resultado->num_rows) {
                    $alertas = Usuario::getAlertas();
                } else {
                    //Hashear el password
                    $usuario->hashPassword();

                    //Generar un token
                    $usuario->crearToken();

                    //Enviar el email
                    $email = new Email(
                        $usuario->email, 
                        $usuario->nombre, 
                        $usuario->token
                    );

                    $email->enviarConfirmacion();

                    //Crear el usuario
                    $resultado = $usuario->guardar();

                    if ($resultado) {
                        header('Location: /mensaje');
                    }

                    //debuguear($usuario);
                }
            }
        }

        $router->render('auth/crear-cuenta', [
           'usuario' => $usuario,
           'alertas' => $alertas
        ]);
        
    }

    public static function confirmar(Router $router) {
        $alertas = [];
        $token = s($_GET['token']);
        $usuario = Usuario::buscarPorCampo('token', $token);

        if(empty($usuario)) {
            // echo 'Token no válido';
            Usuario::setAlerta('error', 'Token no válido');
        } else {
            //Modificar a usuario confirmado
            // echo 'Token válido, confirmando usuario...';
            
            $usuario->confirmado = 1;
            $usuario->token = '';

            // debuguear($usuario);

            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada correctamente');
        }

        //Obtener las alertas
        $alertas = Usuario::getAlertas();

        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
    }

    public static function mensaje(Router $router) {
        $router->render('auth/mensaje');
    }

    public static function admin() {
        echo 'Desde admin';
    }

    public static function cliente() {
        echo 'Desde cliente';
    }

}