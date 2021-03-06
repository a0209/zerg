<?php

namespace app\api\service;

use app\lib\exception\WeChatException;
use think\Exception;
use app\api\model\User as UserModel;
use app\lib\exception\TokenException;
use app\lib\enum\ScopeEnum;

class UserToken extends Token
{
	protected $code;
	protected $wxAppID;
	protected $wxAppSecret;
	protected $wxLoginUrl;

	function __construct($code)
	{
		$this->code = $code;
		$this->wxAppID = config('wx.app_id');
		$this->wxAppSecret = config('wx.app_secret');
		$this->wxLoginUrl = sprintf(config('wx.login_url'),$this->wxAppID,$this->wxAppSecret,$this->code);
	}

	//获取Token令牌
	public function get()
	{
		$result = curl_get($this->wxLoginUrl);
		$wxResult = json_decode($result, true);  
		// 返回的数据: session_key, expire_in(超时时间), openid

		if(empty($wxResult)){
			throw new Exception('获取session_key及openID时异常,微信内部错误');
		}else{
			$loginFail = array_key_exists('errcode', $wxResult);

			if($loginFail){
				// 登录失败
				$this->processLoginError($wxResult);
			}else{
				// 登录成功
				return $this->grantToken($wxResult);
			}
		}
	}

	// 拿到openID
	// 数据库里看一下,这个openID是否已经存在
	// 如果存在,则不处理,如果不存在那么新增一条user记录
	// 生成令牌,准备缓存数据,写入缓存
	// 把令牌返回到客户端去
	// key:令牌
	// value:wxResult,uid,scope
	private function grantToken($wxResult)
	{
		$openid = $wxResult['openid'];
		$user = UserModel::getByOpenId($openid);

		if($user){
			$uid = $user->id;
		}else{
			$uid = $this->newUser($openid);
		}
		$cachedValue = $this->prepareCachedValue($wxResult, $uid);
		$token = $this->saveToCache($cachedValue);

		return $token;
	}

	// 生成缓存数据
	private function saveToCache($cachedValue)
	{
		$key = self::generateToken();
		$value = json_encode($cachedValue);
		$expire_in = config('setting.token_expire_in');
		$request = cache($key, $value, $expire_in);

		if(!$request){
			throw new TokenException([
				'msg' => '服务器异常',
				'errorCode' => 10005
			]);
		}

		return $key;
	}

	// 准备缓存数据
	private function prepareCachedValue($wxResult,$uid)
	{
		$cachedValue = $wxResult;
		$cachedValue['uid'] = $uid;
		// 权限
		// scope = 16 代表App用户的权限数值
		$cachedValue['scope'] = ScopeEnum::User;
		// scope = 32 代表CMS(管理员)用户的权限数值

		return $cachedValue;
	}

	private function newUser($openid)
	{
		$user = UserModel::create([
			'openid' => $openid
		]);

		return $user->id;
	}

	private function processLoginError($wxResult)
	{
		throw new WeChatException([
			'msg' => $wxResult['errmsg'],
			'errorCode' => $wxResult['errcode']
		]);
	}
}