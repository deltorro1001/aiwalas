<?php
namespace App\Libraries;

use RuntimeException;

class TeacherNicknameAccounts
{
    public static function username(array $teacher): string
    {
        $nickname = trim((string)($teacher['nama_panggilan']??''));
        if ($nickname==='') {
            $full = preg_replace('/^(?:(?:drs?|dra|prof)\.?\s+)+/i','',trim((string)$teacher['nama_lengkap']));
            $nickname = preg_split('/\s+/', $full)[0]??'';
        }
        $nickname = preg_replace('/^(?:(?:pak|bu|bapak|ibu|mom)\.?\s+)+/i','',$nickname);
        $username = preg_replace('/[^a-z0-9]/','',strtolower($nickname));
        if ($username==='' || strlen($username)>100) throw new RuntimeException('Nama panggilan guru ID '.$teacher['id'].' tidak dapat dipakai sebagai username.');
        return $username;
    }

    public static function provision($db): int
    {
        $db->transBegin();
        try {
            $teachers = $db->query('SELECT id,nama_lengkap,nama_panggilan,aktif FROM guru ORDER BY id FOR UPDATE')->getResultArray();
            $accounts = $db->query('SELECT id,nama_pengguna,peran,guru_id FROM pengguna FOR UPDATE')->getResultArray();
            $byTeacher=[];$byUsername=[];$planned=[];
            foreach ($accounts as $account) {
                $byUsername[strtolower($account['nama_pengguna'])]=$account;
                if ($account['guru_id']!==null) $byTeacher[(int)$account['guru_id']]=$account;
            }
            foreach ($teachers as $teacher) {
                $username=self::username($teacher);
                if (isset($planned[$username])) throw new RuntimeException('Nama panggilan bentrok: '.$username.' (guru ID '.$planned[$username]['id'].' dan '.$teacher['id'].').');
                $planned[$username]=$teacher;
                if (isset($byTeacher[(int)$teacher['id']]) && $byTeacher[(int)$teacher['id']]['peran']!=='guru') throw new RuntimeException('Guru ID '.$teacher['id'].' terhubung ke akun non-guru.');
            }
            foreach ($planned as $username=>$teacher) {
                $existing=$byUsername[$username]??null;
                if ($existing && ($existing['peran']!=='guru' || !isset($byTeacher[(int)$existing['guru_id']]))) throw new RuntimeException('Username '.$username.' sudah digunakan akun non-guru.');
            }
            // Temporarily rename linked teacher accounts so username swaps stay atomic.
            foreach ($teachers as $teacher) {
                $account=$byTeacher[(int)$teacher['id']]??null;
                if ($account && !$db->table('pengguna')->where('id',$account['id'])->update(['nama_pengguna'=>'teacher-migration-'.bin2hex(random_bytes(16))])) throw new RuntimeException('Gagal menyiapkan akun guru.');
            }
            foreach ($planned as $username=>$teacher) {
                $data=['nama_pengguna'=>$username,'kata_sandi'=>password_hash($username,PASSWORD_DEFAULT),'nama_lengkap'=>$teacher['nama_lengkap'],'peran'=>'guru','guru_id'=>$teacher['id'],'aktif'=>(int)$teacher['aktif']];
                $account=$byTeacher[(int)$teacher['id']]??null;
                $ok=$account?$db->table('pengguna')->where('id',$account['id'])->update($data):$db->table('pengguna')->insert($data);
                if (!$ok) throw new RuntimeException('Gagal menyimpan akun guru.');
            }
            if (!$db->transStatus()) throw new RuntimeException('Transaksi akun guru gagal.');
            $db->transCommit();
            return count($planned);
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
