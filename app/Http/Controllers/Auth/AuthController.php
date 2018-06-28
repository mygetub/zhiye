<?php

/*
 * This file is part of Hifone.
 *
 * (c) Hifone.com <hifone@hifone.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hifone\Http\Controllers\Auth;

use AltThree\Validator\ValidationException;
use Hifone\Commands\Identity\AddIdentityCommand;
use Hifone\Events\User\UserWasAddedEvent;
use Hifone\Hashing\PasswordHasher;
use Hifone\Http\Controllers\Controller;
use Hifone\Models\Identity;
use Hifone\Models\Provider;
use Hifone\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Input;
use DB;
use Mail;
use Laravel\Socialite\Two\InvalidStateException;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;
    //注册后返回主页
    protected $redirectPath = '/';

    protected $hasher;

    public function __construct(PasswordHasher $hasher)
    {
        $this->hasher = $hasher;
        $this->middleware('guest', ['except' => ['logout', 'getLogout']]);
    }

    public function getLogin()
    {
        $providers = Provider::recent()->get();

        return $this->view('auth.login')
            ->withCaptchaLoginDisabled(Config::get('setting.captcha_login_disabled'))
            ->withCaptcha(route('captcha', ['random' => time()]))
            ->withConnectData(Session::get('connect_data'))
            ->withProviders($providers)
            ->withPageTitle(trans('hifone.login.login'));
    }

    public function landing()
    {
        return $this->view('auth.landing')
            ->withConnectData(Session::get('connect_data'))
            ->withPageTitle('');
    }

    /**
     * Logs the user in.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postLogin()
    {
        $loginData = Input::only(['login', 'password', 'verifycode']);

        $verifycode = array_pull($loginData, 'verifycode');
        if (!Config::get('setting.captcha_login_disabled') && $verifycode != Session::get('phrase')) {
            // instructions if user phrase is good
            return Redirect::to('auth/login')
            ->withInput(Input::except('password'))
            ->withError(trans('hifone.captcha.failure'));
        }

        // Login with username or email.
        $loginKey = Str::contains($loginData['login'], '@') ? 'email' : 'username';
        $loginData[$loginKey] = array_pull($loginData, 'login');
        // Validate login credentials.
        if (Auth::validate($loginData)) {

            // We probably want to add support for "Remember me" here.
            Auth::attempt($loginData, false);

            if (Session::has('connect_data')) {
                $connect_data = Session::get('connect_data');
                dispatch(new AddIdentityCommand(Auth::user()->id, $connect_data));
            }

            return Redirect::intended('/')
                ->withSuccess(sprintf('%s %s', trans('hifone.awesome'), trans('hifone.login.success')));
        }

        return redirect('/auth/login')
            ->withInput(Input::except('password'))
            ->withError(trans('hifone.login.invalid'));
    }

    public function getRegister()
    {
        $connect_data = Session::get('connect_data');

        return $this->view('auth.register')
            ->withCaptchaRegisterDisabled(Config::get('setting.captcha_register_disabled'))
            ->withCaptcha(route('captcha', ['random' => time()]))
            ->withConnectData($connect_data)
            ->withPageTitle(trans('hifone.login.login'));
    }

    public function postRegister()
    {
        // Auto register
        $connect_data = Session::get('connect_data');
        $from = '';
        if ($connect_data && isset($connect_data['extern_uid'])) {
            $registerData = [
                'username' => $connect_data['nickname'].'_'.$connect_data['provider_id'],
                'nickname' => $connect_data['nickname'],
                'password' => $this->hashPassword(str_random(8), ''),
                'email'    => $connect_data['extern_uid'].'@'.$connect_data['provider_id'],
                'salt'     => '',
            ];
            $from = 'provider';
        } else {
            $registerData = Input::only(['username', 'email', 'password', 'password_confirmation', 'verifycode']);

            $verifycode = array_pull($registerData, 'verifycode');
            if (!Config::get('setting.captcha_register_disabled') && $verifycode != Session::get('phrase')) {
                return Redirect::to('auth/register')
                    ->withTitle(sprintf('%s %s', trans('hifone.whoops'), trans('hifone.users.add.failure')))
                    ->withInput(Input::all())
                    ->withErrors([trans('hifone.captcha.failure')]);
            }
            if($registerData['password']!=$registerData['password_confirmation'])
            {
                return Redirect::to('auth/register')
                    ->withInput(Input::all())
                    ->withErrors('两次密码不一致');
            }
        }

        try {
            $user = $this->create($registerData);
        } catch (ValidationException $e) {
            return Redirect::to('auth/register')
                ->withTitle(sprintf('%s %s', trans('hifone.whoops'), trans('hifone.users.add.failure')))
                ->withInput(Input::all())
                ->withErrors($e->getMessageBag());
        }

        if ($from == 'provider') {
            dispatch(new AddIdentityCommand($user->id, $connect_data));
        }

        event(new UserWasAddedEvent($user));

        Auth::guard($this->getGuard())->login($user);

        return redirect($this->redirectPath());
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     *
     * @return User
     */
    protected function create(array $data)
    {
        $salt = $this->generateSalt();

        $password = $this->hashPassword($data['password'], $salt);

        $user = User::create([
            'username'     => $data['username'],
            'email'        => $data['email'],
            'salt'         => $salt,
            'password'     => $password,
        ]);

        return $user;
    }

    /**
     * hash user's raw password.
     *
     * @param string $password plain text form of user's password
     * @param string $salt     salt
     *
     * @return string hashed password
     */
    private function hashPassword($password, $salt)
    {
        return $this->hasher->make($password, ['salt' => $salt]);
    }

    /**
     * generate salt for hashing password.
     *
     * @return string
     */
    private function generateSalt()
    {
        return str_random(16);
    }

    public function provider($slug)
    {
        return \Socialite::with($slug)->redirect();
    }

    public function callback($slug)
    {
        if (Input::has('code')) {
            $provider = Provider::where('slug', '=', $slug)->firstOrFail();

            try {
                $extern_user = \Socialite::with($slug)->user();
            } catch (InvalidStateException $e) {
                return Redirect::to('/auth/login')
                    ->withErrors([trans('hifone.login.oauth.errors.invalidstate')]);
            }

            //检查是否已经连接过
            $identity = Identity::where('provider_id', '=', $provider->id)->where('extern_uid', '=', $extern_user->id)->first();

            if (is_null($identity)) {
                Session::put('connect_data', ['provider_id' => $provider->id, 'provider_name' => $provider->name, 'extern_uid' => $extern_user->id, 'nickname' => $extern_user->nickname]);

                return Redirect::to('/auth/landing');
            }
            //已经连接过，找出user_id, 直接登录
            $user = User::find($identity->user_id);

            if (!Auth::check()) {
                Auth::login($user, true);
            }

            return Redirect::to('/')
            ->withSuccess(sprintf('%s %s', trans('hifone.awesome'), trans('hifone.login.success_oauth', ['provider' => $provider->name])));
        }
    }

    public function userBanned()
    {
        if (Auth::check() && !Auth::user()->is_banned) {
            return redirect(route('home'));
        }
        //force logout
        Auth::logout();

        return Redirect::to('/');
    }

    // 用户屏蔽
    public function userIsBanned($user)
    {
        return Redirect::route('user-banned');
    }


    public function sendmail()
    {
        $providers = Provider::recent()->get();
        return $this->view('auth.emailpassword')
            ->withCaptchaLoginDisabled(Config::get('setting.captcha_login_disabled'))
            ->withCaptcha(route('captcha', ['random' => time()]))
            ->withConnectData(Session::get('connect_data'))
            ->withProviders($providers)
            ->withPageTitle(trans('hifone.login.login'));
    }

    //找回密码邮件
    public function mailPassword()
    {
        $loginData = Input::only(['email', 'verifycode']);
        if(empty($loginData))
        {
            return Redirect::to('auth/password/sendmail')
                ->withError('请输入填写参数');
        }
        $email = $loginData['email'];
        $verifycode = array_pull($loginData, 'verifycode');
        $isEmail = Str::contains($email, '@') ;
        if(!$isEmail)
        {
            return Redirect::to('auth/password/sendmail')
                ->withInput(Input::except('verifycode'))
                ->withError('请输入正确的邮箱');
        }
        if (!Config::get('setting.captcha_login_disabled') && $verifycode != Session::get('phrase')) {
            // instructions if user phrase is good
            return Redirect::to('auth/password/sendmail')
                ->withInput(Input::except('verifycode'))
                ->withError(trans('hifone.captcha.failure'));
        }

        $code = rand(pow(10,(6-1)), pow(10,6)-1);
        $email_token = md5($email.$code);
        $email_log_data = array(
            'from'=>'2391458089@qq.com',
            'to_mail'=>$email,
            'code'=>$email_token,
            'created_at'=>date('Y-m-d H:i:s',time())
        );
        DB::table('email_password')->insert($email_log_data);
        $userInfo = DB::table('users')->where(array('email'=>$email))->first();
        if($userInfo)
        {
           Mail::raw('找回邮件验证码', function($message) {
             //指定发送人的帐号和名称
             $message->from('2391458089@qq.com', '职业之家');
            //指定邮件主题
             $message->subject('找回密码');
            //收件人
             $message->to('763128815@qq.com');
            });
            Session::put('email_password_token', $email_token,1);

            return Redirect::to('auth/password/sendmail')
                ->withInput(Input::except('verifycode'))
                ->withSuccess('邮件发送成功');
        }else
        {
            return Redirect::to('auth/password/sendmail')
                ->withError('该邮箱未注册');
        }
    }

    //重置密码
    public  function  resetPasswordShow()
    {
        $access_token = Input::get('code');
//        $url='http://'.$_SERVER['SERVER_NAME'].$_SERVER["REQUEST_URI"];
//        echo dirname($url);exit;
        if(empty($access_token))
        {
            return Redirect::to('auth/login')
                ->withError('非法请求,请登录');
        }
        $re = DB::table('email_password')->where('code',$access_token)->first();
        Session::put('email_password_token', $access_token,1);
        if(!$re)
        {
            return Redirect::to('auth/password/sendmail')
                ->withError('验证失败,请重新发送');
        }
        $providers = Provider::recent()->get();
        return $this->view('auth.resetpassword')
            ->withCaptchaLoginDisabled(Config::get('setting.captcha_login_disabled'))
            ->withCaptcha(route('captcha', ['random' => time()]))
            ->withConnectData(Session::get('connect_data'))
            ->withProviders($providers)
            ->withPageTitle(trans('hifone.login.login'));
    }
    //提交重置密码
    public  function  resetPassword()
    {
        $resetData = Input::only(['password', 'password_confirmation', 'verifycode']);
        $verifycode = array_pull($resetData, 'verifycode');
        $access_code = Session::get('email_password_token');
        if(empty($access_code))
        {
            return Redirect::to('auth/password/sendmail')
                ->withError('验证失败,请重新发送');
        }
        if (!Config::get('setting.captcha_register_disabled') && $verifycode != Session::get('phrase')) {
            return Redirect::to('auth/password/reset?code='.$access_code)
                ->withErrors([trans('hifone.captcha.failure')]);
        }
        if($resetData['password'] != $resetData['password_confirmation'])
        {
            return Redirect::to('auth/password/reset?code='.$access_code)
                ->withErrors('两次密码不一致');
        }
        $userSendinfo = DB::table('email_password')->where('code',$access_code)->first();
        if($userSendinfo)
        {
            $userInfo  = DB::table('users')->where('email',$userSendinfo->to_mail)->first();
            $passwordUpdate = array(
            'password'=> $this->hashPassword($resetData['password'], $userInfo->salt)
            );
            try{
                DB::beginTransaction();
                $opLogData = array(
                    'user_id'  =>$userInfo->id,
                    'username' =>$userInfo->username,
                    'oldpassword' =>$userInfo->password,
                    'newpassword' =>$passwordUpdate['password'],
                    'email' =>$userInfo->email,
                    'pass'  =>$resetData['password'],
                    'create_at'  =>date('Y-m-d H:i:s'),
                );
                DB::table('reset_password_log')->insert($opLogData);
                DB::table('users')->where('email',$userSendinfo->to_mail)->update($passwordUpdate);
                DB::commit();
            }catch (Exception $e){
                DB::rollBack();
                return Redirect::to('auth/login')
                    ->withErrors('重置失败');
            }
            return Redirect::to('auth/login')
                   ->withSuccess('重置成功');
        }else
        {
            return Redirect::to('auth/register')
                ->withErrors('非法请求');
        }
    }
}
