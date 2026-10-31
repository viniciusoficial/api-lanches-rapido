<?php
declare(strict_types=1);
final class Store {
    public function __construct(private string $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar data/');
        foreach (['users'=>[], 'orders'=>[], 'products'=>$this->seedProducts(), 'tokens'=>[]] as $name=>$seed) {
            $p=$this->path($name); if (!file_exists($p)) $this->save($name,$seed);
        }
    }
    private function path(string $name): string { return $this->dir.'/'.$name.'.json'; }
    public function all(string $name): array {
        $f=fopen($this->path($name),'c+'); if (!$f) throw new RuntimeException('Falha ao abrir armazenamento');
        flock($f,LOCK_SH); $raw=stream_get_contents($f); flock($f,LOCK_UN); fclose($f);
        $value=json_decode($raw ?: '[]',true); return is_array($value)?$value:[];
    }
    public function save(string $name,array $data): void {
        $f=fopen($this->path($name),'c+'); if (!$f) throw new RuntimeException('Falha ao gravar armazenamento');
        flock($f,LOCK_EX); ftruncate($f,0); rewind($f); fwrite($f,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)); fflush($f); flock($f,LOCK_UN); fclose($f);
    }
    private function seedProducts(): array { return [
        ['id'=>'p1','name'=>'Lanche Rápido Clássico','category'=>'Hambúrgueres','description'=>'Burger artesanal, queijo, molho da casa.','price'=>24.90,'available'=>true],
        ['id'=>'p2','name'=>'X-Bacon da Chapa','category'=>'X-Bacon','description'=>'Hambúrguer, queijo e bacon crocante.','price'=>29.90,'available'=>true],
        ['id'=>'p3','name'=>'Batata frita','category'=>'Acompanhamentos','description'=>'Porção individual.','price'=>12.00,'available'=>true],
        ['id'=>'p4','name'=>'Refrigerante lata','category'=>'Bebidas','description'=>'350 ml.','price'=>6.00,'available'=>true]
    ]; }
}
