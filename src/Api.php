<?php
declare(strict_types=1);
final class Api {
    public function __construct(private Store $store) {}
    private function out(array $data,int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
    private function body(): array { $x=json_decode(file_get_contents('php://input') ?: '[]',true); return is_array($x)?$x:[]; }
    private function path(): string { return trim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/'); }
    private function user(bool $required=true): ?array {
        $h=$_SERVER['HTTP_AUTHORIZATION']??''; if (!preg_match('/^Bearer\s+(.+)$/i',$h,$m)) { if($required)$this->out(['error'=>'Autenticação necessária'],401); return null; }
        $hash=hash('sha256',trim($m[1])); foreach($this->store->all('tokens') as $t) if(hash_equals($t['hash'],$hash) && $t['expires']>time()) { foreach($this->store->all('users') as $u) if($u['id']===$t['user_id']) return $u; }
        if($required)$this->out(['error'=>'Token inválido ou expirado'],401); return null;
    }
    private function role(array $u,array $roles): void { if(!in_array($u['role'],$roles,true))$this->out(['error'=>'Permissão insuficiente'],403); }
    public function run(): void {
        $method=$_SERVER['REQUEST_METHOD']??'GET'; $path=$this->path(); $b=$this->body();
        if($path==='health'&&$method==='GET')$this->out(['ok'=>true,'service'=>'Lanche Rápido API','storage'=>'JSON']);
        if($path==='auth/register'&&$method==='POST'){
            $name=trim((string)($b['name']??''));$phone=preg_replace('/\D+/','',(string)($b['phone']??''));$pass=(string)($b['password']??'');
            if(strlen($name)<2||strlen($phone)<10||strlen($pass)<8)$this->out(['error'=>'Informe nome, telefone válido e senha com ao menos 8 caracteres'],422);
            $users=$this->store->all('users');foreach($users as $u)if($u['phone']===$phone)$this->out(['error'=>'Telefone já cadastrado'],409);
            $u=['id'=>bin2hex(random_bytes(12)),'name'=>$name,'phone'=>$phone,'password'=>password_hash($pass,PASSWORD_DEFAULT),'role'=>'customer'];$users[]=$u;$this->store->save('users',$users);$this->issueToken($u);
        }
        if($path==='auth/login'&&$method==='POST'){
            $phone=preg_replace('/\D+/','',(string)($b['phone']??''));foreach($this->store->all('users') as $u)if($u['phone']===$phone&&password_verify((string)($b['password']??''),$u['password']))$this->issueToken($u);
            $this->out(['error'=>'Credenciais inválidas'],401);
        }
        if($path==='products'&&$method==='GET')$this->out(['items'=>$this->store->all('products')]);
        if($path==='orders'&&$method==='POST'){
            $u=$this->user();$this->role($u,['customer']);$items=$b['items']??[]; if(!is_array($items)||!count($items))$this->out(['error'=>'Carrinho vazio'],422);
            $products=$this->store->all('products');$lines=[];$total=0;
            foreach($items as $it){$id=(string)($it['product_id']??'');$qty=(int)($it['quantity']??0);$p=null;foreach($products as $candidate)if($candidate['id']===$id&&$candidate['available'])$p=$candidate;if(!$p||$qty<1||$qty>30)$this->out(['error'=>'Produto ou quantidade inválida'],422);$lines[]=['product_id'=>$p['id'],'name'=>$p['name'],'unit_price'=>$p['price'],'quantity'=>$qty];$total+=$p['price']*$qty;}
            $orders=$this->store->all('orders');$id=bin2hex(random_bytes(8));$now=gmdate('c');$order=['id'=>$id,'number'=>strtoupper(substr($id,0,8)),'customer_id'=>$u['id'],'customer_name'=>$u['name'],'items'=>$lines,'notes'=>substr(trim((string)($b['notes']??'')),0,300),'address'=>$b['address']??[],'payment_method'=>in_array(($b['payment_method']??''),['PIX','CASH','CARD'],true)?$b['payment_method']:'CASH','total'=>round($total,2),'status'=>'pending','prep_minutes'=>null,'history'=>[['status'=>'pending','at'=>$now]],'created_at'=>$now,'updated_at'=>$now];$orders[]=$order;$this->store->save('orders',$orders);$this->out(['order'=>$order],201);
        }
        if($path==='orders'&&$method==='GET'){$u=$this->user();$this->role($u,['customer','employee','admin']);$items=array_values(array_filter($this->store->all('orders'),fn($o)=>$u['role']!=='customer'||$o['customer_id']===$u['id']));usort($items,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));$this->out(['items'=>$items]);}
        if(preg_match('#^orders/([a-f0-9]+)/accept$#',$path,$m)&&$method==='POST'){$u=$this->user();$this->role($u,['employee','admin']);$minutes=(int)($b['prep_minutes']??0);if($minutes<1||$minutes>240)$this->out(['error'=>'Tempo deve ser entre 1 e 240 minutos'],422);$this->mutateOrder($m[1],function(&$o)use($minutes){if($o['status']!=='pending')$this->out(['error'=>'Pedido não está aguardando aceite'],409);$o['status']='accepted';$o['prep_minutes']=$minutes;$o['accepted_at']=gmdate('c');});}
        if(preg_match('#^orders/([a-f0-9]+)/reject$#',$path,$m)&&$method==='POST'){$u=$this->user();$this->role($u,['employee','admin']);$this->mutateOrder($m[1],function(&$o){if($o['status']!=='pending')$this->out(['error'=>'Pedido não está aguardando aceite'],409);$o['status']='rejected';});}
        if(preg_match('#^orders/([a-f0-9]+)/status$#',$path,$m)&&$method==='POST'){$u=$this->user();$this->role($u,['employee','admin']);$status=(string)($b['status']??'');$allowed=['preparing','ready','out_for_delivery','delivered'];if(!in_array($status,$allowed,true))$this->out(['error'=>'Status inválido'],422);$this->mutateOrder($m[1],function(&$o)use($status){$next=['accepted'=>'preparing','preparing'=>'ready','ready'=>'out_for_delivery','out_for_delivery'=>'delivered'];if(($next[$o['status']]??null)!==$status)$this->out(['error'=>'Transição de status não permitida'],409);$o['status']=$status;});}
        $this->out(['error'=>'Rota não encontrada'],404);
    }
    private function issueToken(array $u): never { $token=bin2hex(random_bytes(32));$rows=$this->store->all('tokens');$rows[]= ['hash'=>hash('sha256',$token),'user_id'=>$u['id'],'expires'=>time()+60*60*24*30];$this->store->save('tokens',$rows);unset($u['password']);$this->out(['token'=>$token,'user'=>$u]); }
    private function mutateOrder(string $id,callable $fn): never { $rows=$this->store->all('orders');foreach($rows as &$o)if($o['id']===$id){$previous=$o['status'];$fn($o);$o['updated_at']=gmdate('c');$o['history']=$o['history']??[];$o['history'][]=['status'=>$o['status'],'from'=>$previous,'at'=>$o['updated_at']];$this->store->save('orders',$rows);$this->out(['order'=>$o]);}unset($o);$this->out(['error'=>'Pedido não encontrado'],404); }
}
