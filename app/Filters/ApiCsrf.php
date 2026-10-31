<?php
namespace App\Filters;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/** API clients can recover an expired token only when verification rejected the request. */
class ApiCsrf extends \CodeIgniter\Filters\CSRF
{
    public function before(RequestInterface $request,$arguments=null)
    {
        if(!$request instanceof IncomingRequest||!preg_match('#(?:^|/)api/#',$request->getUri()->getPath()))return parent::before($request,$arguments);
        if (str_ends_with($request->getUri()->getPath(), '/api/whatsapp/webhook')) return null;
        try{service('security')->verify($request);}
        catch(SecurityException $error){
            return service('response')->setStatusCode(403)->setHeader('Cache-Control','no-store')->setJSON(['ok'=>false,'code'=>'csrf_expired','message'=>'Token keamanan sudah berubah. Silakan coba kembali.','csrfHash'=>service('security')->getHash()]);
        }
        return null;
    }
}
