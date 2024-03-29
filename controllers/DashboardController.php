<?php

namespace Controllers;

use MVC\Router;
use Model\Usuario;
use Model\Proyecto;

class DashboardController {
    public static function index(Router $router) {
        session_start();
        isAuth();

        $id = $_SESSION['id'];
        $proyectos = Proyecto::belongsTo('propietarioId', $id);
    
        $router->render('dashboard/index', [
            'titulo'=>'Proyectos',
            'proyectos'=>$proyectos
        ]);
    }

    public static function crear_proyecto(Router $router) {
        session_start();
        isAuth();

        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $proyecto = new Proyecto($_POST);
            $alertas = $proyecto->validarProyecto();

            if(empty($alertas)) {
                $hash = md5(uniqid());
                $proyecto->url = $hash;
                $proyecto->propietarioId = $_SESSION['id'];
                $proyecto->guardar();
                header('Location: /proyecto?id=' . $proyecto->url);
            }     
        }

        $router->render('dashboard/crear-proyecto', [
            'titulo'=>'Crear Proyecto',
            'alertas'=>$alertas
        ]);
    }

    public static function proyecto(Router $router) {
        session_start();
        isAuth();

        $token = $_GET['id'];
        if(!$token) header('Location: /dashboard');

        // Revisar que la persona que visita el proyecto es quien lo creó
        $proyecto = Proyecto::where('url', $token);
        if($proyecto->propietarioId !== $_SESSION['id']) {
            header('Location: /dashboard');
        }

        $router->render('dashboard/proyecto', [
            'titulo'=>$proyecto->proyecto
        ]);
    }

    public static function perfil(Router $router) {
        session_start();
        isAuth();

        $alertas = [];
        $usuario = Usuario::find($_SESSION['id']);

        if($_SERVER['REQUEST_METHOD'] === 'POST') { 
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarPerfil();

            if(empty($alertas)) {
                $existeUsuario = Usuario::where('email', $usuario->email);
                if($existeUsuario && $existeUsuario->id !== $usuario->id) {
                    Usuario::setAlerta('error', 'Email ya registrado');
                    $alertas = $usuario->getAlertas();
                } else {
                    $usuario->guardar();
                    Usuario::setAlerta('exito', 'Guardado correctamente');
                    $alertas = $usuario->getAlertas();
                    $_SESSION['nombre'] = $usuario->nombre; // Asignar nombre nuevo a la barra
                }
            }
        }

        $router->render('dashboard/perfil', [
            'titulo'=>'Perfil',
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }

    public static function cambiar_password(Router $router) {
        session_start();
        isAuth();

        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') { 
            $usuario = Usuario::find($_SESSION['id']);
            $usuario->sincronizar($_POST);
            $alertas = $usuario->nuevoPassword();
            
            if(empty($alertas)) {
                $resultado = $usuario->comprobarPassword();
                if($resultado) {
                    $usuario->password = $usuario->password_nuevo;
                    unset($usuario->password_actual);
                    unset($usuario->password_nuevo);
                    $usuario->hashPassword();
                    $resultado = $usuario->guardar();
                    if($resultado) {
                        Usuario::setAlerta('exito', 'Password guardado correctamente');
                        $alertas = $usuario->getAlertas();
                    }
                } else {
                    Usuario::setAlerta('error', 'Password incorrecto');
                    $alertas = $usuario->getAlertas();   
                }
            }      
        }
       
        $router->render('dashboard/cambiar-password', [
            'titulo'=>'Cambiar Password',
            'alertas' => $alertas
        ]);
    }
}