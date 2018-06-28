@extends('layouts.default')

@section('title')
{{ $page->title }} - @parent
@stop

@section('content')

    <div class="panel markdown panel-default">
        <div class="panel-body">
         <b>{!! $page->body !!}</b>
        </div>
    </div>

    <!-- editor start -->
    @include('threads.partials.editor_toolbar')
    <!-- end -->
    <div class="form-group">
        {!! Form::textarea('thread[body]', isset($thread) ? $thread->body_original : $bodyMsg, ['class' => 'post-editor form-control',
                                          'rows' => 15,
                                          'style' => "overflow:hidden",
                                          'id' => 'body_field',
                                          'placeholder' => trans('hifone.markdown_support')]) !!}
    </div>

@stop

