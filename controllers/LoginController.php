<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController {
    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = new Usuario($_POST);
            $alertas = $usuario->validarLogin();

            if(empty($alertas)) {
                $usuario = Usuario::where('email', $usuario->email);
                if(!$usuario || !$usuario->confirmado) {
                    Usuario::setAlerta('error', 'El usuario no existe o no está confirmado');
                } else {
                    if(password_verify($_POST['password'], $usuario->password)) {
                        session_start(); 
                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;
                        header('Location: /dashboard'); // Redireccionar
                    } else {
                        Usuario::setAlerta('error', 'Password incorrecto');
                    }
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login', [
            'titulo'=>'Iniciar Sesión',
            'alertas'=>$alertas
        ]);
    }

    public static function logout() {
        session_start(); 
        $_SESSION = [];
        header('Location: /');
    }

    public static function crear(Router $router) {
        $alertas = [];
        $usuario = new Usuario();
        
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();

            if(empty($alertas)) {
                $existeUsuario = Usuario::where('email', $usuario->email);
                if($existeUsuario) {
                    Usuario::setAlerta('error', 'El usuario ya está registrado');
                    $alertas = Usuario::getAlertas();
                } else {
                    $usuario->hashPassword(); // Hashear password
                    unset($usuario->password2); // Eliminar password2
                    $usuario->crearToken(); // Generar token
                    $resultado = $usuario->guardar(); // Crear un nuevo usuario
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarConfirmacion();
                    if($resultado) {
                        header('Location: /mensaje');
                    }
                }
            }
        }

        $router->render('auth/crear', [
            'titulo'=>'Crear Cuenta',
            'usuario'=>$usuario,
            'alertas'=>$alertas
        ]);
    }

    public static function olvide(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = new Usuario($_POST);
            $alertas = $usuario->validarEmail();

            if(empty($alertas)) {
                $usuario = Usuario::where('email', $usuario->email); // Buscar el usuario
                if($usuario && $usuario->confirmado) {
                    $usuario->crearToken(); // Generar un nuevo token
                    unset($usuario->password2); // Eliminar password2
                    $usuario->guardar(); // Actualizar el usuario
                    Usuario::setAlerta('exito', 'Hemos enviado las instrucciones a tu email'); // Imprimir alerta
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token); // Enviar el email
                    $email->enviarInstrucciones();
                } else {
                    Usuario::setAlerta('error', 'El usuario no existe o no está confirmado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/olvide', [
            'titulo'=>'Olvidé mi Password',
            'alertas'=>$alertas
        ]);
    }

    public static function restablecer(Router $router) {
        $token = s($_GET['token']);
        $mostrar = true;
        if(!$token) header('Location: /');

        $usuario = Usuario::where('token', $token); // Encontrar al usuario con el token

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no válido'); // No se encontró el usuario con ese token
            $mostrar = false;
        } 

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST); // Añadir el nuevo password
            $alertas = $usuario->validarPassword(); // Validar el password

            if(empty($alertas)) {
                $usuario->hashPassword(); // Hashear el nuevo password
                $usuario->token = null; // Eliminar el token
                $resultado = $usuario->guardar(); // Guardar el password en la DB
                if($resultado) header('Location: /');   
            } 
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/restablecer', [
            'titulo'=>'Restablecer Password',
            'alertas'=>$alertas,
            'mostrar'=>$mostrar
        ]);
    }

    public static function mensaje(Router $router) {
        $router->render('auth/mensaje', [
            'titulo'=>'Cuenta Creada Exitosamente'
        ]);
    }

    public static function confirmar(Router $router) {
        $token = s($_GET['token']);
        if(!$token) header('Location: /');

        $usuario = Usuario::where('token', $token); // Encontrar al usuario con el token
        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no válido'); // No se encontró el usuario con ese token
        } else {
            $usuario->confirmado = 1;
            $usuario->token = null;
            unset($usuario->password2);
            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada correctamente');
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/confirmar', [
            'titulo'=>'Confirma tu Cuenta',
            'alertas'=>$alertas
        ]);
    }
}