<?php
// Actual controller, in-memory database and fake sends. No network or application DB.
namespace App\Controllers\Api {
    class BaseApiController {
        public array $input=[];
        public $response;
        protected function payload(){return $this->input;}
        protected function currentUser(){return ['id'=>7,'nama_lengkap'=>'Fixture Wali'];}
        protected function respond($body,$status=200){return compact('body','status');}
    }
}
namespace App\Models {
    class PesanKomunikasiModel {
        public static array $rows=[];
        public function insert($row,$id){self::$rows[]=$row;return count(self::$rows);}
        public function update($id,$row){self::$rows[$id-1]=array_replace(self::$rows[$id-1],$row);}
    }
}
namespace {
    function env($name,$default=null){return $name==='SIMS_WA_GATEWAY_API_KEY'?'':$default;}
    $row=['id'=>1,'session_id'=>'school-session','status'=>'CONNECTED','phone'=>'6281234567890','webhook_token'=>'private-token','webhook_url'=>null];
    class FakeQuery {
        private array $filters=[];
        public function __construct(private string $name){}
        public function where($key,$value=null){$this->filters+=is_array($key)?$key:[$key=>$value];return $this;}
        public function get(){return $this;}
        public function getRowArray(){global $row;return $this->name==='whatsapp_gateway_config'?$row:($this->filters['id']===21?['id'=>21]:null);}
        public function countAllResults(){return $this->filters['id']===1&&$this->filters['wali_kelas_id']===7?1:0;}
    }
    function db_connect(){return new class {public function table($name){return new FakeQuery($name);}};}
    function ensure($condition,$message){if(!$condition)throw new \RuntimeException($message);}
    require dirname(__DIR__).'/app/Libraries/WhatsAppGateway.php';
    require dirname(__DIR__).'/app/Controllers/Api/WhatsAppController.php';
    class TestController extends \App\Controllers\Api\WhatsAppController {
        public int $calls=0;
        public string $outcome='sent';
        protected function gatewayRequest($phone,$message,$type='',$url=''): array {
            $this->calls++;
            ensure(end(\App\Models\PesanKomunikasiModel::$rows)['status_pengiriman']==='Disiapkan','History must precede transport');
            ensure($phone==='6281234567890','Phone not normalized');
            return ['ok'=>$this->outcome==='sent','outcome'=>$this->outcome,'message'=>'Fake gateway result'];
        }
    }
    $controller=new TestController();
    $controller->response=new class {public function setHeader($name,$value){return $this;}};
    $status=$controller->status();
    ensure($status['body']['data']['config']['status']==='CONNECTED'&&$row['session_id']==='school-session','Existing connected session altered');
    ensure(!str_contains(json_encode($status),'private-token')&&!isset($status['body']['data']['config']['api_key']),'Status exposes secrets');
    $controller->input=['kelas_id'=>1,'nomor_tujuan'=>'081234567890','isi_pesan'=>'Fixture message','nama_penerima'=>'Fixture'];
    foreach(['sent'=>'Terkirim','failed'=>'Gagal','unknown'=>'Disiapkan'] as $outcome=>$expected){
        $controller->outcome=$outcome;$result=$controller->send();
        ensure($result['status']===($outcome==='sent'?200:502),'Wrong response status');
        ensure(end(\App\Models\PesanKomunikasiModel::$rows)['status_pengiriman']===$expected,'Wrong communication history');
    }
    $before=$controller->calls;
    $controller->input['kelas_id']=999;ensure($controller->send()['status']===403,'Other class accepted');
    $controller->input['kelas_id']=1;$controller->input['siswa_id']=999;ensure($controller->send()['status']===403,'Other student accepted');
    unset($controller->input['siswa_id']);$controller->input['isi_pesan']=[];ensure($controller->send()['status']===422,'Non-string message accepted');
    ensure($before===$controller->calls,'Invalid requests contacted gateway');
    foreach(['phone','gatewayConfigured','gatewayRequest'] as $method)ensure((new \ReflectionMethod(\App\Controllers\Api\WhatsAppController::class,$method))->isProtected(),'PDF shared helper missing');
    echo "PASS: Existing session preserved, safe status metadata, pending-before-send history, confirmed/failed/unknown outcomes, class/student access and PDF helpers. No network.\n";
}
