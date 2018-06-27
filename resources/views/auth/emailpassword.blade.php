@extends('layouts.default')

@section('content')
<div class="container-fluid">
	<div class="row">
		<div class="col-md-5 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">邮箱找回密码</div>
				<div class="panel-body">
					@if($connect_data)
					<div class="alert alert-info">
						{{ trans('hifone.login.oauth.login.note', ['provider' => $connect_data['provider_name'], 'name' => $connect_data['nickname']]) }}
					</div>
					@endif
					<form role="form" method="POST" action="/auth/password/sendmail">
						<input type="hidden" name="_token" value="{{ csrf_token() }}">
						@if(Session::has('error'))
            				<p class="alert alert-danger">{{ Session::get('error') }}</p>
            			@endif
						<div class="form-group">
							<input type="login" class="form-control" name="email" value="{{ Input::old('email') }}" placeholder="{{ trans('hifone.login.login_placeholder') }}">
						</div>
						@if(!$captcha_login_disabled)
							@include('partials.captcha')
						@endif
						<div class="form-group">
							<input type="submit" name="commit" value="发送" class="btn btn-primary btn-lg btn-block">
						</div>
					</form>
				</div>
				<div class="panel-footer">
					<a href="/auth/register">{{ trans('forms.register') }}</a>
				<a href="/auth/login">登录?</a>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
