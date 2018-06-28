@extends('layouts.default')

@section('content')
<div class="container-fluid">
	<div class="row">
		<div class="col-md-5 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">{{ trans('hifone.login.login') }}</div>
				<div class="panel-body">
					@if($connect_data)
					<div class="alert alert-info">
						{{ trans('hifone.login.oauth.login.note', ['provider' => $connect_data['provider_name'], 'name' => $connect_data['nickname']]) }}
					</div>
					@endif
					<form role="form" method="POST" action="/auth/password/reset">
						<input type="hidden" name="_token" value="{{ csrf_token() }}">
						@if(Session::has('error'))
            				<p class="alert alert-danger">{{ Session::get('error') }}</p>
            			@endif
						<div class="form-group">
							<input type="password" class="form-control" name="password" placeholder="{{ trans('hifone.login.password') }}">
						</div>
						<div class="form-group">
							<input type="password" class="form-control" name="password_confirmation" placeholder="{{ trans('hifone.users.password_confirmation') }}">
						</div>
						@if(!$captcha_login_disabled)
							@include('partials.captcha')
						@endif
						<div class="form-group">
							<input type="submit" name="commit" value="提交" class="btn btn-primary btn-lg btn-block">
						</div>
					</form>
				</div>
				<div class="panel-footer">
					<a href="/auth/register">{{ trans('forms.register') }}</a>
				</div>
			</div>
		</div>3247
		<div class="col-md-3">
			<div class="panel panel-default">
				<div class="panel-heading">{{ trans('hifone.login.login_with_oauth') }}</div>
				<ul class="list-group">
					<li class="list-group-item">
						@foreach($providers as $provider)
						<a href="/auth/{{ $provider->slug }}" class="btn btn-default btn-lg btn-block"><i class="{{ $provider->icon ? $provider->icon : 'fa fa-user' }}"></i> {{ $provider->name }}</a>
						@endforeach
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>
@endsection
