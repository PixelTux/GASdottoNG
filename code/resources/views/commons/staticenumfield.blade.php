<div class="form-group">
	<label class="col-sm-3 control-label">{{ $label }}</label>
	<div class="col-sm-{{ $fieldsize }}">
		<label class="static-label text-muted">
			@foreach($values as $v)
				@if($obj && $obj->$name == $v['value'])
				{{ $v['label'] }}
				@endif
			@endforeach
		</label>
	</div>
</div>
