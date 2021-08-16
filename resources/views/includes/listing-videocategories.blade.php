<button type="button" class="btn btn-primary btn-block mb-4 d-lg-none" type="button" data-toggle="collapse" data-target="#navbarUserHome" aria-controls="navbarCollapse" aria-expanded="false">
		<i class="fa fa-bars myicon-right"></i> {{trans('general.categories')}}
	</button>

	<div class="navbar-collapse collapse d-lg-block" id="navbarUserHome">
	<div class="py-1 mb-4">
	<div class="text-center">
		@foreach (DB::table('videocategories')->where('mode','on')->orderBy('name')->get() as $category)
		<a class="text-muted btn btn-sm bg-white border mb-2 e-none btn-category @if(Request::path() == "videocategory/$category->slug" || Request::path() == "videocategory/$category->slug/new" || Request::path() == "videocategory/$category->slug/free")active-category @endif" href="{{url('videocategory', $category->slug)}}">
			<img src="{{url('public/img-category', $category->image)}}" class="mr-2 rounded" width="30" /> {{ Lang::has('videocategories.' . $category->slug) ? __('videocategories.' . $category->slug) : $category->name }}
		</a>
	@endforeach
</div>
</div>
</div>
