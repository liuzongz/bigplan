微信登陆
1 第一步：用户同意授权，获取code 通过snsapi_userinfo方式获得 并且由于用户同意过，所以无须关注，就可在授权后获取该用户的基本信息。
$this->wxlogin2()           ：微信登陆(入口)
$this->Authorize($url, 0)   ：用户授权,获取code
2 第二步：通过code换取网页授权access_token
$this->access_token($code)  ：根据code获取access_token
3 第三步：刷新access_token
$this->wx_token['add_time'] = $time;  ：刷新access_token
session('wx_token', $this->wx_token); ：存入session
4 第四步：拉取用户信息,通过snsapi_userinfo方式获得
5 第五步：判断用户是否存在，存在：登陆 不存在：注册再登录

登录方式
①：普通登录     is_weixin = false   (不在微信内即是账号密码登录)
②：微信登录     $this->wxlogin()    (有账号用户绑定了微信 用微信自动登录)
③：微信注册登录  $this->wxlogin_reg  (使用微信信息注册 登录)

微信二维码()











