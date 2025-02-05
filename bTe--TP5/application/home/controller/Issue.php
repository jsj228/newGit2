<?php
namespace app\home\controller;

use think\Db;
use think\Exception;

class Issue extends Home
{
	public function index()
	{
		if (!userid()) {
			$this->redirect('/#login');
		}
		
		$where['status'] = array('neq', 0);
		$list = Db::name('Issue')->where($where)->order('tuijian asc,paixu desc,addtime desc')->paginate(5);
		$show = $list->render();
		$tuijian = Db::name('Issue')->where(array("tuijian"=>1))->order("addtime desc")->limit(1)->find();

		if($tuijian){
			$tuijian['coinname'] = config('coin')[$tuijian['coinname']]['title'];
			$tuijian['buycoin']  = config('coin')[$tuijian['buycoin']]['title'];
			$tuijian['bili']     = round(($tuijian['deal'] / $tuijian['num']) * 100, 2);
			$tuijian['content']  = mb_substr(clear_html($tuijian['content']),0,350,'utf-8');
			
			
			$end_ms = strtotime($tuijian['time'])+$tuijian['tian']*3600*24;
			$begin_ms = strtotime($tuijian['time']);
			
			$tuijian['beginTime'] = date("Y-m-d H:i:s",$begin_ms);
			$tuijian['endTime']   = date("Y-m-d H:i:s",$end_ms);
			$tuijian['zhuangtai'] = "进行中" ;
			
			if($begin_ms>time()){
				$tuijian['zhuangtai'] = "尚未开始";//未开始
			}
			
			if($tuijian['num']<=$tuijian['deal']){
				$tuijian['zhuangtai'] =  "已结束";//已结束
			}
			
			if($end_ms<time()){
				$tuijian['zhuangtai'] = "已结束";//已结束
			}
			
			$tuijian['rengou']="";
			if($tuijian['zhuangtai'] == "进行中"){
				$tuijian['rengou']="<a href='/Issue/buy/id/".$tuijian['id'].".html'>立即认购</a>";
			}
		}
		
		if($list){
			$this->assign('prompt_text', model('Text')->get_content('game_issue'));
		}else{
			$this->assign('prompt_text', '');
		}
		
		$list_jinxing = array();
		$list_yure	  = array();
		$list_jieshu  = array();

		foreach ($list as $k => $v) {
			//$list[$k]['img'] = Db::name('Coin')->where(array('name' => $v['coinname']))->value('img');
			
			$list[$k]['bili'] = round(($v['deal'] / $v['num']) * 100, 2);
			$list[$k]['endtime'] = date("Y-m-d H:i:s",strtotime($v['time'])+$v['tian']*3600*24);
			$list[$k]['coinname'] = config('coin')[$v['coinname']]['title'];
			$list[$k]['buycoin']  = config('coin')[$v['buycoin']]['title'];
			$list[$k]['bili']     = round(($v['deal'] / $v['num']) * 100, 2);
			$list[$k]['content']  = mb_substr(clear_html($v['content']),0,350,'utf-8');
			
			$end_ms = strtotime($v['time'])+$v['tian']*3600*24;
			$begin_ms = strtotime($v['time']);

			$list[$k]['beginTime'] = date("Y-m-d H:i:s",$begin_ms);
			$list[$k]['endTime']   = date("Y-m-d H:i:s",$end_ms);
			$list[$k]['zhuangtai'] = "进行中" ;
			
			if($begin_ms>time()){
				$list[$k]['zhuangtai'] = "尚未开始";//未开始
			}
			
			if($list[$k]['num']<=$list[$k]['deal']){
				$list[$k]['zhuangtai'] =  "已结束";//已结束
			}

			if($end_ms<time()){
				$list[$k]['zhuangtai'] = "已结束";//已结束
			}
			
			switch($list[$k]['zhuangtai']){
				case "尚未开始":
					$list_yure[] = $list[$k];
					break;
				case "进行中":	
					$list_jinxing[] = $list[$k];
					break;
				case "已结束":
					$list_jieshu[] = $list[$k];
					break;
			}
		}
		
		$this->assign('tuijian', $tuijian);
		$this->assign('list_yure', $list_yure);
		$this->assign('list_jinxing', $list_jinxing);
		$this->assign('list_jieshu', $list_jieshu);
		$this->assign('page', $show);
		return $this->fetch();
	}

	public function buy()
	{
        $id = input('id/d', 1);
		if (!userid()) {
			$this->redirect('/#login');
		}

		$this->assign('prompt_text', model('Text')->get_content('game_issue_buy'));

		if (!check($id, 'd')) {
			$this->error('参数错误！');
		}

		$Issue = Db::name('Issue')->where(array('id' => $id))->find();
		$Issue['bili'] = round(($Issue['deal'] / $Issue['num']) * 100, 2);
		
		$end_ms = strtotime($Issue['time'])+$Issue['tian']*3600*24;
		$begin_ms = strtotime($Issue['time']);
		$Issue['status'] = 1 ;
		
		if($begin_ms>time()){
			$Issue['status'] = 2;//未开始
		}

		if($Issue['num']==$Issue['deal']){
			$Issue['status'] = 0;//已结束
		}
		
		if($end_ms<time()){
			$Issue['status'] = 0;//已结束
		}

		$Issue['endtime'] = date("Y-m-d H:i:s",strtotime($Issue['time'])+$Issue['tian']*3600*24);
		$user_coin = Db::name('UserCoin')->where(array('userid' => userid()))->find();
		$this->assign('user_coin', $user_coin);

		if (!$Issue) {
			$this->error('认购错误！');
		}
		$Issue['img'] = Db::name('Coin')->where(array('name' => $Issue['coinname']))->value('img');
		$this->assign('issue', $Issue);
		return $this->fetch();
	}

	public function log()
	{
        $ls = input('ls/d', 15);
		if (!userid()) {
			$this->redirect('/#login');
		}

		$this->assign('prompt_text', model('Text')->get_content('game_issue_log'));
		$where['status'] = array('egt', 0);
		$where['userid'] = userid();
		$IssueLog = Db::name('IssueLog');
		$list = $IssueLog->where($where)->order('id desc')->paginate($ls);
		$show = $list->render();

		foreach ($list as $k => $v) {
			$list[$k]['shen'] = round((($v['ci'] - $v['unlock']) * $v['num']) / $v['ci'], 6);
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		return $this->fetch();
	}

	public function alllog()
	{
        $ls = input('ls/d', 15);
		if (!userid()) {
			$this->redirect('/#login');
		}

		$this->assign('prompt_text', model('Text')->get_content('game_issue_log'));
		$where['status'] = array('egt', 0);
		$IssueLog = Db::name('IssueLog');
		$list = $IssueLog->where($where)->order('id desc')->paginate($ls);
		$show = $list->render();

		foreach ($list as $k => $v) {
			$list[$k]['shen'] = round((($v['ci'] - $v['unlock']) * $v['num']) / $v['ci'], 6);
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		return $this->fetch();
	}

	public function upbuy()
	{
        $id = input('id/d');
        $num = input('num/f');
        $paypassword = input('paypassword/s');

		if (!userid()) {
			$this->redirect('/#login');
		}

		if (!check($id, 'd')) {
			$this->error('参数错误！');
		}

		if (!check($num, 'd')) {
			$this->error('认购数量格式错误！');
		}

		if (!check($paypassword, 'password')) {
			$this->error('交易密码格式错误！');
		}

		$User = Db::name('User')->where(array('id' => userid()))->find();

		if (!$User['paypassword']) {
			$this->error('交易密码非法！');
		}

		if (md5($paypassword) != $User['paypassword']) {
			$this->error('交易密码错误！');
		}

		$Issue = Db::name('Issue')->where(array('id' => $id))->find();

		if (!$Issue) {
			$this->error('认购错误！');
		}

		if (time() < strtotime($Issue['time'])) {
			$this->error('当前认购还未开始！');
		}
		
		if (!$Issue['status']) {
			$this->error('当前认购已经结束！');
		}

		$end_ms = strtotime($Issue['time'])+$Issue['tian']*3600*24;
		
		if($end_ms<time()){
			$this->error('当前认购已经结束！');
		}

		$issue_min = ($Issue['min'] ? $Issue['min'] : 9.9999999999999995E-7);
		$issue_max = ($Issue['max'] ? $Issue['max'] : 100000000);

		if ($num < $issue_min) {
			$this->error('单次认购数量不得少于系统设置' . $issue_min . '个');
		}

		if ($issue_max < $num) {
			$this->error('单次认购数量不得大于系统设置' . $issue_max . '个');
		}

		if (($Issue['num'] - $Issue['deal']) < $num) {
			$this->error('认购数量超过当前剩余量！');
		}

		$mum = round($Issue['price'] * $num, 6);
		if (!$mum) {
			$this->error('认购总额错误');
		}

		$buycoin = Db::name('UserCoin')->where(array('userid' => userid()))->value($Issue['buycoin']);
		if ($buycoin < $mum) {
			$this->error('可用' . config('coin')[$Issue['buycoin']]['title'] . '余额不足');
		}

		$issueLog = Db::name('IssueLog')->where(array('userid' => userid(), 'coinname' => $Issue['coinname']))->sum('num');
		if ($Issue['limit'] < ($issueLog + $num)) {
			$this->error('认购总数量超过最大限制' . $Issue['limit']);
		}

        $tm = $end_ms - ($Issue['jian'] * 3600);     //认购结束时间就是解冻时间
        Db::startTrans();
		try {
            $rs = [];
            $finance = Db::table('weike_finance')->lock(true)->where(array('userid' => userid()))->order('id desc')->find();
            $finance_num_user_coin = Db::table('weike_user_coin')->lock(true)->where(array('userid' => userid()))->find();
            $rs[] = Db::table('weike_user_coin')->where(array('userid' => userid()))->setDec($Issue['buycoin'], $mum);
            $rs[] = $finance_nameid = Db::table('weike_issue_log')->insertGetId(array('userid' => userid(), 'coinname' => $Issue['coinname'], 'buycoin' => $Issue['buycoin'], 'name' => $Issue['name'], 'price' => $Issue['price'], 'num' => $num, 'mum' => $mum, 'ci' => $Issue['ci'], 'jian' => $Issue['jian'], 'unlock' => 0, 'addtime' => time(), 'endtime' => $tm, 'status' => 0));
            $finance_mum_user_coin = Db::table('weike_user_coin')->where(array('userid' => userid()))->find();
            $finance_hash = md5(userid() . $finance_num_user_coin['hkd'] . $finance_num_user_coin['hkdd'] . $mum . $finance_mum_user_coin['hkd'] . $finance_mum_user_coin['hkdd'] . MSCODE . 'auth.weike.com');
            $rs[] = Db::table('weike_finance')->insert(array('userid' => userid(), 'coinname' => 'hkd', 'num_a' => $finance_num_user_coin['hkd'], 'num_b' => $finance_num_user_coin['hkdd'], 'num' => $finance_num_user_coin['hkd'] + $finance_num_user_coin['hkdd'], 'fee' => $mum, 'type' => 2, 'name' => 'issue', 'nameid' => $finance_nameid, 'remark' => '认购中心-立即认购', 'mum_a' => $finance_mum_user_coin['hkd'], 'mum_b' => $finance_mum_user_coin['hkdd'], 'mum' => $finance_mum_user_coin['hkd'] + $finance_mum_user_coin['hkdd'], 'move' => $finance_hash, 'addtime' => time(), 'status' => $finance['mum'] != $finance_num_user_coin['hkd'] + $finance_num_user_coin['hkdd'] ? 0 : 1));
            $rs[] = Db::table('weike_issue')->where(array('id' => $id))->setInc('deal', $num);

            if ($Issue['num'] <= $Issue['deal']) {
                $rs[] = Db::table('weike_issue')->where(array('id' => $id))->setField('status', 0);
            }

            if ($User['invit_1'] && $Issue['invit_1']) {
                $invit_num_1 = round(($num / 100) * $Issue['invit_1'], 6);

                if ($invit_num_1) {
                    $rs[] = Db::table('weike_user_coin')->where(array('userid' => $User['invit_1']))->setInc($Issue['invit_coin'], $invit_num_1);
                    $rs[] = Db::table('weike_invit')->insert(array('userid' => $User['invit_1'], 'invit' => userid(), 'name' => $Issue['name'], 'type' => '一代认购赠送', 'num' => $num, 'mum' => $mum, 'fee' => $invit_num_1, 'addtime' => time(), 'status' => 1));
                }
            }

            if ($User['invit_2'] && $Issue['invit_2']) {
                $invit_num_2 = round(($num / 100) * $Issue['invit_2'], 6);

                if ($invit_num_2) {
                    $rs[] = Db::table('weike_user_coin')->where(array('userid' => $User['invit_2']))->setInc($Issue['invit_coin'], $invit_num_2);
                    $rs[] = Db::table('weike_invit')->insert(array('userid' => $User['invit_2'], 'invit' => userid(), 'name' => $Issue['name'], 'type' => '二代认购赠送', 'num' => $num, 'mum' => $mum, 'fee' => $invit_num_2, 'addtime' => time(), 'status' => 1));
                }
            }

            if ($User['invit_3'] && $Issue['invit_3']) {
                $invit_num_3 = round(($num / 100) * $Issue['invit_3'], 6);

                if ($invit_num_3) {
                    $rs[] = Db::table('weike_user_coin')->where(array('userid' => $User['invit_3']))->setInc($Issue['invit_coin'], $invit_num_3);
                    $rs[] = Db::table('weike_invit')->insert(array('userid' => $User['invit_3'], 'invit' => userid(), 'name' => $Issue['name'], 'type' => '三代认购赠送', 'num' => $num, 'mum' => $mum, 'fee' => $invit_num_3, 'addtime' => time(), 'status' => 1));
                }
            }

            if (check_arr($rs)) {
                Db::commit();
                $this->success('购买成功！');
            } else {
                Db::rollback();
                $this->error('购买失败!');
            }
        }catch (Exception $e){
		    Db::rollback();
            exception_log($e,__FUNCTION__);
            $this->error('购买失败!');
        }
	}

	public function unlock()
	{
        $id = input('id/d');
		if (!userid()) {
			$this->redirect('/#login');
		}

		if (!check($id, 'd')) {
			$this->error('请选择解冻项！');
		}

		$IssueLog = Db::name('IssueLog')->where(array('id' => $id))->find();

		if (!$IssueLog) {
			$this->error('参数错误！');
		}

		if ($IssueLog['status']) {
			$this->error('当前解冻已完成！');
		}

		if ($IssueLog['ci'] <= $IssueLog['unlock']) {
			$this->error('非法访问！');
		}

		$tm = $IssueLog['endtime'] + (60 * 60 * $IssueLog['jian']);

		if (time() < $tm) {
			$this->error('解冻时间还没有到,请在<br>【' . addtime($tm) . '】<br>之后再次操作');
		}

		if ($IssueLog['userid'] != userid()) {
			$this->error('非法访问');
		}

		$jd_num = round($IssueLog['num'] / $IssueLog['ci'], 6);

        Db::startTrans();
		try {
            $rs = [];
            $rs[] = Db::table('weike_user_coin')->where(array('userid' => userid()))->setInc($IssueLog['coinname'], $jd_num);
            $rs[] = Db::table('weike_issue_log')->where(array('id' => $IssueLog['id']))->update(array('unlock' => $IssueLog['unlock'] + 1, 'endtime' => $tm));

            if ($IssueLog['ci'] <= $IssueLog['unlock'] + 1) {
                $rs[] = Db::table('weike_issue_log')->where(array('id' => $IssueLog['id']))->update(array('status' => 1));
            }

            if (check_arr($rs)) {
                Db::commit();
                $this->success('解冻成功！');
            } else {
                Db::rollback();
                $this->error('解冻失败！');
            }
        }catch (Exception $e){
		    Db::rollback();
            exception_log($e,__FUNCTION__);
            $this->error('解冻失败！');
        }
	}
}

?>