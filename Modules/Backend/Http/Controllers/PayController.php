<?php
/**
 * 支付
 * Author: ck
 * Date: 2018/1/20
 */

namespace Modules\Backend\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Backend\Models\Df;
use Modules\Backend\Models\Order;
use Modules\Backend\Models\User;
use Storage;

class PayController extends Controller
{

    public function __construct()
    {
        $config = array(
            'appid' => 'wxf592d3f69391cdbf',//小程序appid
            'pay_mchid' => '1485571792',//商户号
            'pay_apikey' => '08ef9657dce4c78ddde83c3ba84679b1',//可在微信商户后台生成支付秘钥
            'secret' => '45b54e4ca2e9090f1f3e0318c24c4417',
        );
        $this->config = $config;
    }

    /**
     * 银行卡认证
     * @return array
     */
    public function bankType(Request $request)
    {
        $params = $request->all();
        $url = "http://bankcardsilk.api.juhe.cn/bankcardsilk/query.php";
        if (!isset($params['bankcard'])) {
            return ['code' => 90002, 'msg' => '银行卡号码必填'];
        }
        if (!isset($params['bank_name'])) {
            return ['code' => 90002, 'msg' => '银行卡类别必填'];
        }
        $data['num'] = $params['bankcard'];
        $data['key'] = '3b4eeae287592abe7cca48ea4fbd8b0d';
        $datatring = http_build_query($data);
        $content = $this->juhecurl($url, $datatring);
        $result = json_decode($content, true);
        if (mb_substr($result['result']['bank'], -4) == trim($params['bank_name'])) {
            return ['code' => 1, 'msg' => '验证一致'];
        } else {
            return ['code' => 90002, 'msg' => '验证不一致'];
        }
    }

    /**
     * 银行卡认证
     * @return array
     */
    public function bankVerify(Request $request)
    {
        $params = $request->all();
        $url = "http://v.juhe.cn/verifybankcard/query";
        if (!isset($params['bankcard'])) {
            return ['code' => 90002, 'msg' => '银行卡号码必填'];
        }
        if (!isset($params['realname'])) {
            return ['code' => 90002, 'msg' => '银行卡所属人必填'];
        }
        $data = array(
            "bankcard" => $params['bankcard'],
            "realname" => $params['realname'],
            "key" => 'b201d873830ed7709ff68ea1a61cfa9d',
        );
        $datatring = http_build_query($data);
        $content = $this->juhecurl($url, $datatring);
        $result = json_decode($content, true);
        if ($result['result']['res'] === 1) {
            return ['code' => 1, 'msg' => '验证一致'];
        } else {
            return ['code' => 90002, 'msg' => '验证不一致'];
        }
    }

    /**
     * 请求接口返回内容
     * @param  string $url [请求的URL地址]
     * @param  string $params [请求的参数]
     * @param  int $ipost [是否采用POST形式]
     * @return  string
     */
    public function juhecurl($url, $params = false, $ispost = 0)
    {
        $httpInfo = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JuheData');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }

    /**
     * 敏感字过滤
     * @return array
     */
    public function filterWord(Request $request)
    {
        $params = $request->all();
        if (!isset($params['word'])) {
            return ['code' => 90002, 'msg' => '需过滤文字不能为空'];
        }
        $data = [
            'str' => $params['word'],
            'lv' => 1
        ];
        $url = 'http://www.hoapi.com/index.php/Home/Api/check';
        $res = json_decode($this->curl($url, $data), true);
        if ($res['status']) {
            return ['code' => 1, 'msg' => '检测通过'];
        }
        return ['code' => 90002, 'msg' => '含有敏感字'];
    }


    #获取小程序二维码
    public function getQrcodeConfig(Request $request)
    {
        $params = $request->all();
        $config = $this->config;
        if (!isset($params['path'])) {
            return ['code' => 90002, 'msg' => 'path不能为空'];
        }
        #access_token
        $urlaccess_token = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $config['appid'] . '&secret=' . $config['secret'] . '';
        $access_token = json_decode($this->requestGet($urlaccess_token), true);
        $token = $access_token['access_token'];
        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $token . '';
        $path_data = '{"scene":"' . $this->getRangChar() . '","path": "' . $params['path'] . '", "width": 430,"auto_color":false,"line_color":{"r":"0","g":"0","b":"0"}}';
        $res = $this->curl($url, $path_data);
        $data = 'data:image/jpg/png/gif;base64,' . base64_encode($res) . '';
        return ['code' => 1, 'data' => $data];
    }


    #获取小程序二维码
    public function getQrcodeConfig2($params)
    {
        $config = $this->config;
        if (!isset($params['path'])) {
            return ['code' => 90002, 'msg' => 'path不能为空'];
        }
        #access_token
        $urlaccess_token = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $config['appid'] . '&secret=' . $config['secret'] . '';
        $access_token = json_decode($this->requestGet($urlaccess_token), true);
        $token = $access_token['access_token'];
        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $token . '';
        $path_data = '{"scene":"' . $this->getRangChar() . '","path": "' . $params['path'] . '", "width": 430,"auto_color":false,"line_color":{"r":"0","g":"0","b":"0"}}';
        $res = $this->curl($url, $path_data);
        $data = 'data:image/jpg;base64,' . base64_encode($res) . '';
        $file_url = $this->base64_image_content($data, '' . storage_path() . '/app/wx/');
        return $file_url;
    }

    #base64->img
    function base64_image_content($base64_image_content, $path)
    {
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $new_file = $path;
            if (!file_exists($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($new_file, 0755);
            }
            $file_name = time() . ".{$type}";
            $new_file = $new_file . $file_name;
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                return $file_name;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    function http_post_json($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array($httpCode, $response);
    }

    /**
     * 体现到银行卡
     * @return array
     * openssl rsa -RSAPublicKey_in -in <filename> -pubout
     */
    public function bankCard(Request $request)
    {
        $params = $request->all();
        if (!isset($params['bankcard'])) {
            return ['code' => 90002, 'msg' => '银行卡号码必填'];
        }
        if (!isset($params['openid'])) {
            return ['code' => 90002, 'msg' => 'openid必填'];
        }
        if (!isset($params['realname'])) {
            return ['code' => 90002, 'msg' => '银行卡所属人必填'];
        }
        if (!isset($params['amount'])) {
            return ['code' => 90002, 'msg' => '提现金额不能为空'];
        }
        if ($params['amount'] > 1) {
            return ['code' => 90002, 'msg' => '提现金额大于1'];
        }
        if (!isset($params['bank_name'])) {
            return ['code' => 90002, 'msg' => '银行卡类别必填'];
        }
        $bank_code = self::bank_sn($params['bank_name']);
        if (!$bank_code) {
            return ['code' => 90002, 'msg' => '银行卡类型不支持'];
        }
        #判断是否存在此用户 账户余额
        $exitUsr = User::where('openid', $params['openid'])->first();
        if (!$exitUsr) {
            return ['code' => 90002, 'msg' => '用户不存在'];
        }
        if ($exitUsr['user_account'] > $params['amount']) {
            $config = $this->config;
            $data = [
                'mch_id' => $config['pay_mchid'],
                'partner_trade_no' => 'HB' . time(),
                'nonce_str' => $this->getRangChar(),
                'enc_bank_no' => $this->rsa_encrypt($params['bankcard']),
                'enc_true_name' => $this->rsa_encrypt($params['realname']),
                'bank_code' => $bank_code,
                'amount' => $params['amount'] * 100,
                'desc' => '提现'
            ];
            $data['sign'] = self::makeSign($data);
            #数组转xml
            $xmldata = self::array2xml($data);
            $url = 'https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank';
            $res = self::curl($url, $xmldata);
            #xml转数组
            $content = self::xml2array($res);
            if ($content['result_code'] == 'FAIL') {
                return $this->refunErr($content['err_code']);
            }
            #提现记录
            $dfdata['openid'] = $params['openid'];
            $dfdata['df_money'] = $params['amount'];
            Df::dfAdd($dfdata);
            $user['openid'] = $params['openid'];
            $user['user_account'] = $exitUsr['user_account'] - $params['amount'];
            User::userEdit($user);
            return $content;
        } else {
            return ['code' => 90002, 'msg' => '您的余额不足'];
        }
    }

    public function rsa_encrypt($str)
    {
        $pu_key = openssl_pkey_get_public(file_get_contents(getcwd() . '/cert/public.pem'));  //读取公钥内容
        $encryptedBlock = '';
        $encrypted = '';
        openssl_public_encrypt($str, $encryptedBlock, $pu_key, OPENSSL_PKCS1_OAEP_PADDING);
        $str_base64 = base64_encode($encrypted . $encryptedBlock);
        return $str_base64;
    }

    public function bank_sn($bank_name)
    {
        switch (trim($bank_name)) {
            case '工商银行':
                return 1002;
                break;
            case '农业银行':
                return 1005;
                break;
            case '中国银行':
                return 1026;
                break;
            case '建设银行':
                return 1003;
                break;
            case '招商银行':
                return 1001;
                break;
            case '邮储银行':
                return 1066;
                break;
            case '交通银行':
                return 1020;
                break;
            case '浦发银行':
                return 1004;
                break;
            case '民生银行':
                return 1006;
                break;
            case '兴业银行':
                return 1009;
                break;
            case '平安银行':
                return 1010;
                break;
            case '中信银行':
                return 1021;
                break;
            case '华夏银行':
                return 1025;
                break;
            case '广发银行':
                return 1027;
                break;
            case '光大银行':
                return 1022;
                break;
            case '宁波银行':
                return 1056;
                break;
            case '北京银行':
                return 1032;
                break;
        }
        return false;
    }

    #获取rsa公钥
    protected function bankRSA()
    {
        $config = $this->config;
        $url = 'https://fraud.mch.weixin.qq.com/risk/getpublickey';
        $data = [
            'mch_id' => $config['pay_mchid'],
            'nonce_str' => $this->getRangChar(),
            'sign_type' => 'MD5'
        ];
        $data['sign'] = self::makeSign($data);
        #数组转xml
        $xmldata = self::array2xml($data);
        $res = self::curl($url, $xmldata);
        #xml转数组
        $content = self::xml2array($res);
        Storage::disk('public')->put('public.pem', $content['pub_key']);
        return 1;
    }


    #提现
    public function getRefundConfig(Request $request)
    {
        $params = $request->all();
        if (!isset($params['openid'])) {
            return ['code' => 90002, 'msg' => 'openid不存在'];
        }
        if (!isset($params['amount'])) {
            return ['code' => 90002, 'msg' => '提现金额不存在'];
        }
        if ($params['amount'] > 1) {
            return ['code' => 90002, 'msg' => '提现金额大于1'];
        }
        #判断是否存在此用户 账户余额
        $exitUsr = User::where('openid', $params['openid'])->first();
        if (!$exitUsr) {
            return ['code' => 90002, 'msg' => '用户不存在'];
        }
        if ($exitUsr['user_account'] > $params['amount']) {
            $config = $this->config;
            $order_sn = 'HB' . date('YmdHis', time());
            $data = [
                'mch_appid' => $config['appid'],
                'mchid' => $config['pay_mchid'],
                'nonce_str' => $this->getRangChar(),
                'partner_trade_no' => $order_sn,
                'openid' => $params['openid'],
                'check_name' => 'NO_CHECK',
                're_user_name' => 'DaTi',
                'amount' => $params['amount'] * 100,
                'desc' => '提现费用',
                'spbill_create_ip' => $request->getClientIp(),
            ];
            $data['sign'] = self::makeSign($data);
            #数组转xml
            $xmldata = self::array2xml($data);
            $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
            $res = self::curl($url, $xmldata);
            if (!$res) {
                return ['code' => 90002, 'msg' => '系统错误,请稍后再试'];
            }
            #xml转数组
            $content = self::xml2array($res);
            #错误提示
            return $content;
            if ($content['result_code'] == 'FAIL') {
                return $this->refunErr($content['err_code']);
            }
            #提现记录
            $dfdata['openid'] = $params['openid'];
            $dfdata['df_money'] = $params['amount'];
            Df::dfAdd($dfdata);
            $user['openid'] = $params['openid'];
            $user['user_account'] = $exitUsr['user_account'] - $params['amount'];
            User::userEdit($user);
            return $content;
        } else {
            return ['code' => 90002, 'msg' => '您的余额不足'];
        }
    }

    #体现错误码
    public function refunErr($params)
    {
        $err = ['NO_AUTH', 'AMOUNT_LIMIT', 'PARAM_ERROR', 'OPENID_ERROR', 'SEND_FAILED', 'NOTENOUGH', 'SYSTEMERROR', 'NAME_MISMATCH'
            , 'SIGN_ERROR', 'XML_ERROR', 'FATAL_ERROR', 'FREQ_LIMIT', 'MONEY_LIMIT', 'CA_ERROR', 'V2_ACCOUNT_SIMPLE_BAN', 'PARAM_IS_NOT_UTF8', 'AMOUNT_LIMIT'];
        if (in_array($params, $err)) {
            return ['code' => 90002, 'msg' => '请求超时,请稍后再试'];
        }
    }


    public function curl($url, $xmldata, $second = 30, $aHeader = array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);

        curl_setopt($ch, CURLOPT_SSLCERT, getcwd() . '/cert/apiclient_cert.pem');
        curl_setopt($ch, CURLOPT_SSLKEY, getcwd() . '/cert/apiclient_key.pem');

        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }


    #支付配置
    public function getPayConfig(Request $request)
    {
        $params = $request->all();
        if (!isset($params['order_sn'])) {
            return ['code' => 90002, 'msg' => '订单号不存在'];
        }
        if (!isset($params['openid'])) {
            return ['code' => 90002, 'msg' => 'openid不存在'];
        }
        $order = Order::where('order_sn', $params['order_sn'])->first();
        $params['count_money'] = $order['count_money'];
        $config = $this->config;
        $data = [
            'appid' => $config['appid'],
            'mch_id' => $config['pay_mchid'],
            'nonce_str' => $this->getRangChar(),
            'body' => '答题红包',
            'out_trade_no' => $params['order_sn'],
            'total_fee' => $params['count_money'] * 100,
            'spbill_create_ip' => $request->getClientIp(),
            'notify_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/backend/pay-notify',
            'trade_type' => 'JSAPI',
            'openid' => $params['openid'],
        ];
        #签名
        $data['sign'] = self::makeSign($data);
        #数组转xml
        $xmldata = self::array2xml($data);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $res = self::curl_post_ssl($url, $xmldata);
        if (!$res) {
            return ['code' => 90002, 'msg' => '支付失败'];
        }
        #xml转数组
        $content = self::xml2array($res);
        $result = $this->pay($content);
        return ['code' => 1, 'data' => $result];
    }

//post
    protected function curl_post_ssl($url, $xmldata, $second = 30, $aHeader = array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);


        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }


    public function pay($content)
    {
        $config = $this->config;
        $data = array(
            'appId' => $content['appid'],
            'timeStamp' => time(),
            'nonceStr' => $this->getRangChar(),
            'package' => 'prepay_id=' . $content['prepay_id'],
            'signType' => 'MD5'
        );
        $data['paySign'] = self::makeSign($data);
        return $data;
    }


    #随机生成32位字符串
    public function getRangChar($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    protected function makeSign($data)
    {
        //获取微信支付秘钥
        $key = $this->config['pay_apikey'];
        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp = $string_a . "&key=" . $key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }


    //回调
    public function notify()
    {
        return 123;
    }

    # 将xml转为array
    protected function xml2array($xml)
    {
        //禁止引用外部xml实体
        (true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }


    # 将一个数组转换为 XML 结构的字符串
    protected function array2xml($arr, $level = 1)
    {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }

    function requestGet($url)
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $file_contents = curl_exec($ch);
        curl_close($ch);
        return $file_contents;
    }

    function requestPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

}
