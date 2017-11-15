<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * API.php.
 *
 * @author    AC <alexever@gmail.com>
 * @copyright 2015 overtrue <i@overtrue.me>
 *
 * @see      https://github.com/overtrue
 * @see      http://overtrue.me
 */

namespace EasyWeChat\Payment\MerchantPay;

use EasyWeChat\Core\AbstractAPI;
use EasyWeChat\Payment\Merchant;
use EasyWeChat\Support\Collection;
use EasyWeChat\Support\XML;
use Psr\Http\Message\ResponseInterface;

/**
 * Class API.
 */
class API extends AbstractAPI
{
    /**
     * Merchant instance.
     *
     * @var Merchant
     */
    protected $merchant;

    // api
    const API_SEND = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
    const API_QUERY = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo';
    const API_GETPUBLICKEY = 'https://fraud.mch.weixin.qq.com/risk/getpublickey';
    const API_PAYBANK = 'https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank';
    const API_QUERYBANK = 'https://api.mch.weixin.qq.com/mmpaysptrans/query_bank';

    /**
     * API constructor.
     *
     * @param \EasyWeChat\Payment\Merchant $merchant
     */
    public function __construct(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * Query MerchantPay.
     *
     * @param string $mchBillNo
     *
     * @return \EasyWeChat\Support\Collection
     *
     * @notice mch_id when query, but mchid when send
     */
    public function query($mchBillNo)
    {
        $params = [
            'appid' => $this->merchant->app_id,
            'mch_id' => $this->merchant->merchant_id,
            'partner_trade_no' => $mchBillNo,
        ];

        return $this->request(self::API_QUERY, $params);
    }

    /**
     * Send MerchantPay.
     *
     * @param array $params
     *
     * @return \EasyWeChat\Support\Collection
     */
    public function send(array $params)
    {
        $params['mchid'] = $this->merchant->merchant_id;
        $params['mch_appid'] = $this->merchant->app_id;

        return $this->request(self::API_SEND, $params);
    }

    /**
     * getPublicKey MerchantPay.
     * Notice: the key format is pcks1, you should convert it to pcks8
     * 
     * @return \EasyWeChat\Support\Collection
     *
     */
    public function getPublicKey()
    {
        $params = [
            'mch_id' => $this->merchant->merchant_id,
        ];

        return $this->request(self::API_GETPUBLICKEY, $params);
    }

    /**
     * Send MerchantPay.
     *
     * @param array $params
     *
     * @return \EasyWeChat\Support\Collection
     */
    public function sendBank(array $params)
    {
        // $response = $this->getPublicKey();
        // $public_key = trim($response['pub_key'],"\n");

        $filename =  realpath(\Yii::getAlias('@backend').'/../certs/wxpay.pcks8.pem');
        $pu_key = file_get_contents($filename);
        openssl_public_encrypt($params['enc_bank_no'], $enc_bank_no, $pu_key, OPENSSL_PKCS1_OAEP_PADDING);//公钥加密  
        $enc_bank_no = base64_encode($enc_bank_no);
        openssl_public_encrypt($params['enc_true_name'], $enc_true_name, $pu_key, OPENSSL_PKCS1_OAEP_PADDING);//公钥加密  
        $enc_true_name = base64_encode($enc_true_name);  

        $params['mch_id'] = $this->merchant->merchant_id;
        $params['enc_bank_no'] = $enc_bank_no;
        $params['enc_true_name'] = $enc_true_name;

        return $this->request(self::API_PAYBANK, $params);
    }

    public function queryBank($mchBillNo)
    {
        $params = [
            'mch_id' => $this->merchant->merchant_id,
            'partner_trade_no' => $mchBillNo,
        ];

        return $this->request(self::API_QUERYBANK, $params);
    }

    /**
     * Merchant setter.
     *
     * @param Merchant $merchant
     *
     * @return $this
     */
    public function setMerchant(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * Merchant getter.
     *
     * @return Merchant
     */
    public function getMerchant()
    {
        return $this->merchant;
    }

    /**
     * Make a API request.
     *
     * @param string $api
     * @param array  $params
     * @param string $method
     *
     * @return \EasyWeChat\Support\Collection
     */
    protected function request($api, array $params, $method = 'post')
    {
        $params = array_filter($params);
        $params['nonce_str'] = uniqid();
        $params['sign'] = \EasyWeChat\Payment\generate_sign($params, $this->merchant->key, 'md5');

        $options = [
            'body' => XML::build($params),
            'cert' => $this->merchant->get('cert_path'),
            'ssl_key' => $this->merchant->get('key_path'),
        ];

        return $this->parseResponse($this->getHttp()->request($api, $method, $options));
    }

    /**
     * Parse Response XML to array.
     *
     * @param ResponseInterface $response
     *
     * @return \EasyWeChat\Support\Collection
     */
    protected function parseResponse($response)
    {
        if ($response instanceof ResponseInterface) {
            $response = $response->getBody();
        }

        return new Collection((array) XML::parse($response));
    }
}
