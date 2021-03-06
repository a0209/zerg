<?php

namespace app\api\controller\v1;

use app\api\controller\BaseController;
use app\api\validate\IDMustBePostiveInt;
use app\api\validate\OrderPlace;
use app\api\validate\PagingParamter;
use app\api\service\Token as TokenService;
use app\api\service\Order as OrderService;
use app\api\model\Order as OrderModel;
use app\lib\exception\OrderException;
use app\lib\exception\SuccessMessage;

class Order extends BaseController{
	// 用户在选择商品后,向api提交包含它所选择商品的相关信息
	// api在接收到信息后,需要检查订单相关商品的库存量
	// 有库存,把订单数据存入到数据库中 = 下单成功了,返回客户端消息,告诉客户端可以支付了
	// 调用我们的支付接口,进行支付
	// 还需要再次进行库存量检测
	// 服务器这边就可以调用的微信的支付接口进行支付
	// 小程序根据服务器返回的结果拉起微信支付
	// 微信会返回给我们一个支付的结果(异步) 返回两个,一个到客户端,一个到服务器端
	// 成功:也需要进行库存量的检测
	// 成功:进行库存量的扣除

	// 另一种思路处理库存量的问题
	// 先库存量检测
	// 创建订单
	// 减库存 -- 预扣除库存
	// if pay 真正的减库存
	// 在一定时间内(15min)没有支付这个订单, 我们需要还原库存

	// 还原的方法
	// 在PHP里写一个定时器, 每隔1min去遍历数据库,
	// 找到那些超时的订单, 把这些订单给还原库存
	// linux crontab

	// 任务队列
	// 订单任务加入到队列里
	// redis

	// 定时器, 每隔1s, 5s, 访问API的接口

	protected $beforeActionList = [
		'checkExclusiveScope' => ['only' => 'placeOrder'],
		'checkPrimaryScope' => ['only' => 'getSummaryByUser, getDetail'],
	];

	// 下单
	public function placeOrder()
	{
		(new OrderPlace())->gocheck();
		$products = input('post.products/a');
		$uid = TokenService::getCurrentUid();

		$order = new OrderService();
		$status = $order->place($uid, $products);

		return $status;
	}

	/**
	 * @param int $page
	 * @param int $size
	 * 获取历史订单
	 * @return array
	 * @throws \app\lib\exception\ParameterException
	 */
	public function getSummaryByUser($page=1, $size=5)
	{
		(new PagingParamter())->goCheck();
		$uid = TokenService::getCurrentUid();
		$pagingOrders = OrderModel::getSummaryByUser($uid, $page, $size);

		if($pagingOrders->isEmpty()){

			return [
				'data' => [],
				'current_page' => $pagingOrders->getCurrentPage()
			];
		}
		$data = $pagingOrders->hidden(['snap_items','snap_address','prepay_id'])
			->toArray();

		 return [
		 	'data' => $data,
		 	'current_page' => $pagingOrders->getCurrentPage()
		 ];
	}

	/**
	 * @param $id
	 * 订单详情
	 * @return OrderModel
	 * @throws OrderException
	 * @throws \app\lib\exception\ParameterException
	 * @throws \think\exception\DbException
	 */
	public function getDetail($id)
	{
		(new IDMustBePostiveInt())->goCheck();
		$orderDetail = OrderModel::get($id);

		if(!$orderDetail){
			throw new OrderException();
		}

		return $orderDetail->hidden(['prepay_id']);
	}

	/**
	 * 获取全部订单简要信息(分页)-cms
	 * @param int $page
	 * @param int $size
	 * @return array
	 * @throws \app\lib\exception\ParameterException
	 */
	public function getSummary($page, $size = 10)
	{
		(new PagingParamter())->goCheck();

		$pagingOrders = OrderModel::getSummaryByPage($page, $size);

		if($pagingOrders->isEmpty()){
			return [
				'current_page' => $pagingOrders->currentPage(),
				'data' => []
			];
		}
		$data = $pagingOrders->hidden(['snap_items', 'snap_address'])
			->toArray();

		return [
			'current_page' => $pagingOrders->currentPage(),
			'data' => $data
		];
	}

	/**
	 * 发送模板消息
	 * @param int $id - 订单号Id
	 */
	public function delivery($id)
	{
		(new IDMustBePostiveInt())->goCheck();

		$order = new OrderService();
		$success = $order->delivery($id);

		if($success){
			return new SuccessMessage();
		}
	}
}