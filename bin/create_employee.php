<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if($argc<4){fwrite(STDERR,"Uso: php bin/create_employee.php \"Nome\" telefone senha\nSenha: mínimo de 12 caracteres.\n");exit(2);}
$name=trim($argv[1]);$phone=preg_replace('/\D+/','',$argv[2]);$password=$argv[3];if(strlen($name)<2||strlen($phone)<10||strlen($password)<12){fwrite(STDERR,"Dados inválidos: informe nome, telefone com DDD e senha de pelo menos 12 caracteres.\n");exit(2);}
require __DIR__.'/../src/Store.php';$store=new Store(__DIR__.'/../data');$users=$store->all('users');foreach($users as $u)if($u['phone']===$phone){fwrite(STDERR,"Telefone já cadastrado.\n");exit(1);}$users[]=['id'=>bin2hex(random_bytes(12)),'name'=>$name,'phone'=>$phone,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'employee'];$store->save('users',$users);fwrite(STDOUT,"Conta de funcionário criada.\n");
