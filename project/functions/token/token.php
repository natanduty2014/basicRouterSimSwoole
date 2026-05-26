<?php

namespace Functions\token;

class token
{
    private static $publicKey;
    private static $privateKey;
    private static function public()
    {
      static::$publicKey = <<<EOD
      -----BEGIN PUBLIC KEY-----
      MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA0g6omdj+Wv42GQvUxZtK
      tzcWDwdmrPaYlGgpO+2R7b0dTacDOancx5Z1+KvytkAZGSuZyv/pj2hV4rGzVTLW
      HOH7xiwAJzhkyixamT406/dB/52U0HjrPxsWW237xybb8MKm7Lybh1W3EOr4WIzj
      FJxlgtJMXJFZXWTDQ1+HVird5FyDdgdecf/gwaQdK3gDx7a06vWT//z98kNNpTmz
      /VoibF8Zd0BCLXgHnnVMawiB1cCAieBHBFEMVq7ebazv8nxqMFk0AG3bQRF2SheO
      76bGjsY0I6fh+ZVyFRdIAhDYRDoBwVqqSzCjn72vBRd03exh334xWKMULuX2TuGd
      3pkzHUHnHQKXUbcTDXlpcrD05+YKxepAUskR0t2eT+YpWHcfm91q0qsQzWSjW8hF
      J7YQar8Dn8tBlEcbbSaJGFvcmtQ3oIWswS79X3NF2nMTYea+3dM8gmbLql7p8gka
      hTsXVzwn+9UcVmTC42attF7ITybryMML9H0iqZQdSE5iCzw1Or9EarfObcnfudHl
      DcDm5maFanLMxuxeRa5XnAGA96xL58uO3KWMBuMB99hX3lL2XM6EPnrooHAasox/
      ZJ5zijGcuYG7KOZb6TwChRTixEzo0E1Enb0wL/XI8odKW+pWX+akxFvlNw1ysOm9
      J1roMI4+UoolwIBVD5H/4SUCAwEAAQ==
      -----END PUBLIC KEY-----
      EOD;
return static::$publicKey;
    }
    public static function publickey(){
        return self::public();
    }
    //
    private static function private()
    {
      return static::$privateKey = <<<EOD
      -----BEGIN RSA PRIVATE KEY-----
      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
      -----END RSA PRIVATE KEY-----
      EOD;
    }

    public static function privatekey(){
        return self::private();
    }
}
