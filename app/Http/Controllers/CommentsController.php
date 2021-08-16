<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use App\Models\User;
use App\Models\AdminSettings;
use App\Models\Comments;
use App\Models\Notifications;
use App\Models\Updates;
use App\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class CommentsController extends Controller
{
	 public function __construct( AdminSettings $settings, Request $request)
	 {
		 $this->settings = $settings::first();
		 $this->request = $request;
	}

	 protected function validator(array $data)
	 {
    	Validator::extend('ascii_only', function($attribute, $value, $parameters){
    		return !preg_match('/[^x00-x7F\-]/i', $value);
		});

		$messages = array (
    'comment.required' => trans('general.please_write_something'),
    );

			return Validator::make($data, [
	        	'comment' =>  'required|max:'.$this->settings->comment_length.'|min:2',
	        ], $messages);

    }

	 /**
   * Store a newly created resource in storage.
   *
   * @return Response
   */
	 public function store(Request $request)
	 {
		 $input = $request->all();

		 $validator = $this->validator($input);

	   $update = Updates::where('id', $request->update_id)->first();

	   if ( ! isset($update)) {
	   		return response()->json([
			        'success' => false,
			        'errors' => ['error' => trans('general.error')],
			    ]);
				exit;
	   }

	    if ($validator->fails()) {
	        return response()->json([
			        'success' => false,
			        'errors' => $validator->getMessageBag()->toArray(),
			    ]);
	    }

		$sql            = new Comments;
		$sql->reply     = trim(Helper::checkTextDb($request->comment));
		$sql->updates_id = $request->update_id;
		$sql->user_id   = auth()->user()->id;
		$sql->save();

		$idComment = $sql->id;

		/*------* SEND NOTIFICATION * ------*/

		if (auth()->user()->id != $update->user_id  && $update->user()->notify_commented_post == 'yes') {
			// Send Notification //destination, author, type, target
			Notifications::send($update->user_id, auth()->user()->id, '3', $update->id);
		}

		$nameUser = auth()->user()->hide_name == 'yes' ? auth()->user()->username : auth()->user()->name;

		return response()->json([
    'success' => true,
    'total' => number_format( $update->comments()->count()),
    'data' => '<div class="comments media li-group pt-3 pb-3" data="'.$idComment.'">
			<a class="float-left" href="'.url(auth()->user()->username).'">
				<img class="rounded-circle mr-3" src="'.Helper::getFile(config('path.avatar').auth()->user()->avatar).'" width="40"></a>
				<div class="media-body">
					<h6 class="media-heading mb-0">
					<a href="'.url(auth()->user()->username).'">
						'.$nameUser.'</a></h6>
						<p class="list-grid-block p-text mb-0 text-word-break">'.Helper::linkText(Helper::checkText($sql->reply)).'</p>
						<span class="small sm-font sm-date text-muted timeAgo" data="'.date('c', time()).'"></span>
						<span class="c-pointer small sm-font delete-comment font-weight-bold" data="'.$idComment.'"><i class="feather icon-trash-2"></i></span>
					</div><!-- media-body -->
				</div>',
			    ]);

	}//<--- End Method


	/**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return Response
   */
	public function destroy($id)
	{
		$comment = Comments::findOrFail($id);

		if ($comment->user_id == auth()->user()->id || $comment->updates()->user_id == auth()->user()->id) {

			// Delete Notification
			Notifications::where('author', $comment->user_id)
			->where('target', $comment->updates_id)
			->where('created_at', $comment->date)
			->delete();

			$comment->delete();

			$countComments = Comments::where('updates_id',$comment->updates_id)->count();

			return response()->json( array( 'success' => true, 'total' => Helper::formatNumber($countComments) ) );

		} else {
			return response()->json( array( 'success' => false, 'error' => trans('general.error') ) );
		}

	}//<--- End Method

	/**
   * Load More Comments
   *
   * @param  \Illuminate\Http\Request  $request
   * @return Response
   */
	public function loadmore(Request $request)
	{
		$id       = $request->input('id');
		$postId   = $request->input('post');
		$skip     = $request->input('skip');
		$response = Updates::findOrFail($postId);

		$page  = $request->input('page');
		$comments = $response->comments()->skip($skip)->take($this->settings->number_comments_show)->orderBy('id', 'DESC')->get();
	  $data = [];

	  if ($comments->count()) {
	      $data['reverse'] = collect($comments->values())->reverse();
	  } else {
	      $data['reverse'] = $comments;
	  }

	  $dataComments = $data['reverse'];
		$counter = ($response->comments()->count() - $this->settings->number_comments_show - $skip);

		return response()->json([
			'comments' => view('includes.comments',
					[
						'dataComments' => $dataComments,
						'comments' => $comments,
						'response' => $response,
						'counter' => $counter
						]
					)->render()
		]);

	}//<--- End Method

}
