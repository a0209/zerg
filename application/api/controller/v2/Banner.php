<?php
namespace app\api\controller\v2;

use think\Exception;

class Banner
{
	/*
	获取指定id的banner信息
	@url /banner/:id
	@http get
	@id banner的id号
	*/
	public function getBanner($id)
	{
		return 'This is v2 version';
	}
}