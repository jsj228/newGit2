<?php
namespace app\home\controller;
use app\home\controller\HomeCommon;
use think\Request;
use think\Db;
use think\Cache;
use think\sessoin;

class Pay extends HomeCommon
{
	public function index()
	{
		if (IS_POST) {
			if (isset($_POST['alipay'])) {
                $alipay = input('alipay/s');
				$arr = explode('--', $alipay);

				if (md5('weike') != $arr[2]) {
					echo -1;
					exit();
				}

				if (!strstr($arr[0], 'Pay')) {
				}

				$arr[0] = trim(str_replace(PHP_EOL, '', $arr[0]));
				$arr[1] = trim(str_replace(PHP_EOL, '', $arr[1]));

				if (strstr($arr[0], '付款-')) {
					$arr[0] = str_replace('付款-', '', $arr[0]);
				}

				$mycz = Db::name('Mycz')->where(['tradeno' => $arr[0]])->find();

				if (!$mycz) {
					echo -3;
					exit();
				}

				if (($mycz['status'] != 0) && ($mycz['status'] != 3)) {
					echo -4;
					exit();
				}

				if ($mycz['num'] != $arr[1]) {
					echo -5;
					exit();
				}

				$mo = Db::name('');
				$mo->startTrans();
				try{
                    $finance = Db::table('weike_finance')->where(['userid' => $mycz['userid']])->order('id desc')->find();
                    $finance_num_user_coin = Db::table('weike_user_coin')->where(['userid' => $mycz['userid']])->find();
                    Db::table('weike_user_coin')->where(['userid' => $mycz['userid']])->setInc('cny', $mycz['num']);
                    Db::table('weike_mycz')->where(['id' => $mycz['id']])->update(['status' => 1, 'mum' => $mycz['num'], 'endtime' => time()]);
                    $finance_mum_user_coin = Db::table('weike_user_coin')->where(['userid' => $mycz['userid']])->find();
                    $finance_hash = md5($mycz['userid'] . $finance_num_user_coin['cny'] . $finance_num_user_coin['cnyd'] . $mycz['num'] . $finance_mum_user_coin['cny'] . $finance_mum_user_coin['cnyd'] . MSCODE . 'auth.weike.com');
                    $finance_num = $finance_num_user_coin['cny'] + $finance_num_user_coin['cnyd'];

                    if ($finance['mum'] < $finance_num) {
                        $finance_status = (1 < ($finance_num - $finance['mum']) ? 0 : 1);
                    } else {
                        $finance_status = (1 < ($finance['mum'] - $finance_num) ? 0 : 1);
                    }

                    Db::table('weike_finance')->insert([
                        'userid' => $mycz['userid'],
                        'coinname' => 'cny',
                        'num_a' => $finance_num_user_coin['cny'],
                        'num_b' => $finance_num_user_coin['cnyd'],
                        'num' => $finance_num_user_coin['cny'] + $finance_num_user_coin['cnyd'],
                        'fee' => $mycz['num'],
                        'type' => 1,
                        'name' => 'mycz',
                        'nameid' => $mycz['id'],
                        'remark' => '人民币充值-人工到账', 'mum_a' => $finance_mum_user_coin['cny'],
                        'mum_b' => $finance_mum_user_coin['cnyd'],
                        'mum' => $finance_mum_user_coin['cny'] + $finance_mum_user_coin['cnyd'],
                        'move' => $finance_hash,
                        'addtime' => time(), 'status' => $finance_status
                    ]);
                    $flag = true;
                    $mo->commit();
                }catch (\Exception $e){
				    $flag = false;
				    $mo->rollback();
                }

				if ($flag) {
					echo 1;
					exit();
				} else {
					echo -6;
					exit();
				}
			}
		}
	}

	public function movepay()
	{
		if (IS_POST) {
			$movepay = input('movepay/s');
			$tradeno = input('tradeno/s');
			$num = input('num/s');

			if (md5('weike') != $movepay) {
				echo -1;
				exit();
			}

			$mycz = Db::name('Mycz')->where(['tradeno' => $tradeno])->find();

			if (!$mycz) {
				echo -2;
				exit();
			}

			if ($mycz['status']) {
				echo -3;
				exit();
			}

			if ($mycz['num'] != $num) {
				echo -4;
				exit();
			}

			$mo = Db::name('');
			$mo->startTrans();
			try{
                Db::table('weike_user_coin')->where(['userid' => $mycz['userid']])->setInc('cny', $mycz['num']);
                Db::table('weike_mycz')->where(['id' => $mycz['id']])->update(['status' => 1, 'mum' => $mycz['num'], 'endtime' => time()]);
                $flag = true;
                $mo->commit();
            }catch (\Exception $e){
			    $flag = false;
			    $mo->rollback();
            }


			if ($flag) {
				$this->redirect('Mycz/log');
				exit();
			} else {
				echo -5;
				exit();
			}
		}
	}

	public function mycz()
	{
	    $id = input('id');
		if (check($id, 'd')) {
			$mycz = Db::name('Mycz')->where(['id' => $id])->find();

			if (!$mycz) {
				$this->redirect('/Finance/mycz');
			}

			$myczType = Db::name('MyczType')->where(['id' => $mycz['bank_id']])->find();

			$this->assign('myczType', $myczType);
			$this->assign('mycz', $mycz);
			return $this->fetch($mycz['type']);
		} else {
			return $this->redirect('/Finance/mycz');
		}
	}

	public function ecpss()
	{
        $id = input('id/d', NULL);
        $uid = userid();
		if (!$uid) {
			$this->error('请先登录！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}

		$mycz = Db::name('Mycz')->where(['id' => $id])->find();

		if (!$mycz) {
			$this->error('订单不存在！');
		}

		if ($mycz['userid'] != userid()) {
			$this->error('参数非法！');
		}

		$this->error('订单不存在！');
	}
	//支付宝------》
	 //在类初始化方法中，引入相关类库    
       public function _initialize() {
        vendor('Alipay.Corefunction');
        vendor('Alipay.Md5function');
        vendor('Alipay.Notify');
        vendor('Alipay.Submit');    
       }
    
    //doalipay方法
        /*该方法其实就是将接口文件包下alipayapi.php的内容复制过来
          然后进行相关处理
        */
    public function doalipay(){
            /*********************************************************
            把alipayapi.php中复制过来的如下两段代码去掉，
            第一段是引入配置项，
            第二段是引入submit.class.php这个类。
           为什么要去掉？？
            第一，配置项的内容已经在项目的Config.php文件中进行了配置，我们只需用C函数进行调用即可；
            第二，这里调用的submit.class.php类库我们已经在PayAction的_initialize()中已经引入；所以这里不再需要；
            *****************************************************/
       // require_once("alipay.config.php");
       // require_once("lib/alipay_submit.class.php");
       
       //这里我们通过TP的C函数把配置项参数读出，赋给$alipay_config；
       $alipay_config=config::get('alipay_config');
        /**************************请求参数**************************/

        $payment_type = "1"; //支付类型 //必填，不能修改
        $notify_url = config::get('alipay.notify_url'); //服务器异步通知页面路径
        $return_url = config::get('alipay.return_url'); //页面跳转同步通知页面路径
        $seller_email = config::get('alipay.seller_email');//卖家支付宝帐户必填
 
        
   /*     $out_trade_no = $_POST['trade_no'];//商户订单号 通过支付页面的表单进行传递，注意要唯一！
        $subject = $_POST['ordsubject'];  //订单名称 //必填 通过支付页面的表单进行传递
        $total_fee = $_POST['ordtotal_fee'];   //付款金额  //必填 通过支付页面的表单进行传递
        $body = $_POST['orDbody'];  //订单描述 通过支付页面的表单进行传递
        $show_url = $_POST['ordshow_url'];  //商品展示地址 通过支付页面的表单进行传递
        $anti_phishing_key = "";//防钓鱼时间戳 //若要使用请调用类文件submit中的query_timestamp函数
        $exter_invoke_ip = get_client_ip(); //客户端的IP地址  */
		$v_d ='商户订单号';
		$v_n ='订单名称';
		$v_p ='100';
		$v_c ='订单描述';
        $out_trade_no = $v_d;//$_POST['v_d']// '商户订单号';//商户订单号 通过支付页面的表单进行传递，注意要唯一！
        $subject = $v_n;//'订单名称';  //订单名称 //必填 通过支付页面的表单进行传递
        $total_fee =$v_p;//'100';   //付款金额  //必填 通过支付页面的表单进行传递
        $body = $v_c;//'订单描述';  //订单描述 通过支付页面的表单进行传递
        $show_url = '商品展示地址';  //商品展示地址 通过支付页面的表单进行传递
        $anti_phishing_key = "";//防钓鱼时间戳 //若要使用请调用类文件submit中的query_timestamp函数
        $exter_invoke_ip = get_client_ip(); //客户端的IP地址
        
        /************************************************************/
    
	        //构造要请求的参数数组，无需改动
	    $parameter = array(
	        "service" => "create_direct_pay_by_user",
	        "partner" => trim($alipay_config['partner']),
	        "payment_type"    => $payment_type,
	        "notify_url"    => $notify_url,
	        "return_url"    => $return_url,
	        "seller_email"    => $seller_email,
	        "out_trade_no"    => $out_trade_no,
	        "subject"    => $subject,
	        "total_fee"    => $total_fee,
	        "body"            => $body,
	        "show_url"    => $show_url,
	        "anti_phishing_key"    => $anti_phishing_key,
	        "exter_invoke_ip"    => $exter_invoke_ip,
	        "_input_charset"    => trim(strtolower($alipay_config['input_charset']))
	        );

	        //建立请求
	        $alipaySubmit = new \AlipaySubmit($alipay_config);
	        $html_text = $alipaySubmit->buildRequestForm($parameter,"post", "确认");
	        echo $html_text;
	    }
    
    
        /******************************
        服务器异步通知页面方法
        其实这里就是将notify_url.php文件中的代码复制过来进行处理
        
        *******************************/
    function notifyurl(){
                /*
                同理去掉以下两句代码；
                */ 
                //require_once("alipay.config.php");
                //require_once("lib/alipay_notify.class.php");
                
                //这里还是通过C函数来读取配置项，赋值给$alipay_config
        $alipay_config=config::get('alipay_config');

        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();

        if($verify_result) {
               //验证成功
                   //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
           $out_trade_no   = $_POST['out_trade_no'];      //商户订单号
           $trade_no       = $_POST['trade_no'];          //支付宝交易号
           $trade_status   = $_POST['trade_status'];      //交易状态
           $total_fee      = $_POST['total_fee'];         //交易金额
           $notify_id      = $_POST['notify_id'];         //通知校验ID。
           $notify_time    = $_POST['notify_time'];       //通知的发送时间。格式为yyyy-MM-dd HH:mm:ss。
           $buyer_email    = $_POST['buyer_email'];       //买家支付宝帐号；
            $parameter = array(
             "out_trade_no"     => $out_trade_no, //商户订单编号；
             "trade_no"     => $trade_no,     //支付宝交易号；
             "total_fee"     => $total_fee,    //交易金额；
             "trade_status"     => $trade_status, //交易状态
             "notify_id"     => $notify_id,    //通知校验ID。
             "notify_time"   => $notify_time,  //通知的发送时间。
             "buyer_email"   => $buyer_email,  //买家支付宝帐号；
           );
           if($_POST['trade_status'] == 'TRADE_FINISHED') {
                       //
           }else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {                           if(!checkorderstatus($out_trade_no)){
               orderhandle($parameter); 
                           //进行订单处理，并传送从支付宝返回的参数；
               }
            }
                echo "success";        //请不要修改或删除
         }else {
                //验证失败
                echo "fail";
        }    
    }
    
    /*
        页面跳转处理方法；
        这里其实就是将return_url.php这个文件中的代码复制过来，进行处理； 
        */
    function returnurl(){
                //头部的处理跟上面两个方法一样，这里不罗嗦了！
        $alipay_config=config::get('alipay_config');
        $alipayNotify = new \AlipayNotify($alipay_config);//计算得出通知验证结果
        $verify_result = $alipayNotify->verifyReturn();
        if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表
        $out_trade_no   = $_GET['out_trade_no'];      //商户订单号
        $trade_no       = $_GET['trade_no'];          //支付宝交易号
        $trade_status   = $_GET['trade_status'];      //交易状态
        $total_fee      = $_GET['total_fee'];         //交易金额
        $notify_id      = $_GET['notify_id'];         //通知校验ID。
        $notify_time    = $_GET['notify_time'];       //通知的发送时间。
        $buyer_email    = $_GET['buyer_email'];       //买家支付宝帐号；
            
        $parameter = array(
            "out_trade_no"     => $out_trade_no,      //商户订单编号；
            "trade_no"     => $trade_no,          //支付宝交易号；
            "total_fee"      => $total_fee,         //交易金额；
            "trade_status"     => $trade_status,      //交易状态
            "notify_id"      => $notify_id,         //通知校验ID。
            "notify_time"    => $notify_time,       //通知的发送时间。
            "buyer_email"    => $buyer_email,       //买家支付宝帐号
        );
        
if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
        if(!checkorderstatus($out_trade_no)){
             orderhandle($parameter);  //进行订单处理，并传送从支付宝返回的参数；
    }
        $this->redirect(config::get('alipay.successpage'));//跳转到配置项中配置的支付成功页面；
    }else {
        echo "trade_status=".$_GET['trade_status'];
        $this->redirect(config::get('alipay.errorpage'));//跳转到配置项中配置的支付失败页面；
    }
}else {
    //验证失败
    //如要调试，请看alipay_notify.php页面的verifyReturn函数
    echo "支付失败！";
    }
}
}

?>