<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminSettings;
use App\Models\Conversations;
use App\Models\Messages;
use App\Models\User;
use App\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Image;
use Cache;

class MessagesController extends Controller
{

  // Subscribed to your Content
  protected function subscribedToYourContent($user)
  {
    return auth()->user()
      ->mySubscriptions()
        ->where('user_id', $user->id)
        ->where('stripe_id', '=', '')
        ->whereDate('ends_at', '>=', today()->toDateString())
        ->whereStripePlan(auth()->user()->plan)

        ->orWhere('stripe_status', 'active')
          ->where('user_id', auth()->user()->id)
          ->where('stripe_id', '<>', '')
          ->whereStripePlan($user->plan)

          ->orWhere('stripe_status', 'canceled')
            ->where('user_id', auth()->user()->id)
            ->whereDate('ends_at', '>=', today()->toDateString())
            ->where('stripe_id', '<>', '')
            ->whereStripePlan($user->plan)

          ->orWhere('stripe_id', '=', '')
        ->where('stripe_plan', $user->plan)
        ->where('free', '=', 'yes')
      ->whereUserId(auth()->user()->id)
      ->first();
  }

  // Subscribed to my Content
  protected function subscribedToMyContent($user)
  {
    return auth()->user()
      ->userSubscriptions()
      ->whereStripePlan($user->plan)
      ->where('stripe_id', '=', '')
      ->whereDate('ends_at', '>=', today()->toDateString())

      ->orWhere('stripe_status', 'active')
        ->where('stripe_id', '<>', '')
        ->where('user_id', $user->id)
        ->whereStripePlan(auth()->user()->plan)

        ->orWhere('stripe_status', 'canceled')
          ->where('stripe_id', '<>', '')
          ->where('user_id', $user->id)
          ->whereDate('ends_at', '>=', today()->toDateString())
          ->whereStripePlan(auth()->user()->plan)

        ->orWhere('stripe_id', '=', '')
      ->where('stripe_plan', auth()->user()->plan)
      ->where('free', '=', 'yes')
    ->whereUserId($user->id)
    ->first();
  }

  /**
	 * Display all messages inbox
	 *
	 * @return Response
	 */
  public function inbox()
  {
			$settings = AdminSettings::first();

			$messages = Conversations::has('messages')->where('user_1', Auth::user()->id)
			->orWhere('user_2', Auth::user()->id)
			->orderBy('updated_at', 'DESC')
			->paginate(10);

      if (request()->ajax()) {
              return view('includes.messages-inbox',['messages' => $messages])->render();
          }

		   return view('users.messages', ['messages' => $messages]);

	}//<--- End Method inbox

  /**
	 * Section chat
   *
	 * @param int  $id
	 * @return Response
	 */
  public function messages($id)
  {
    $user = User::whereId($id)->where('id', '<>', Auth::user()->id)->firstOrFail();

			$allMessages = Messages::where('to_user_id', Auth::user()->id)
			->where('from_user_id', $id)
			->orWhere( 'from_user_id', Auth::user()->id )
			->where('to_user_id', $id)
			->orderBy('messages.updated_at', 'ASC')
			->get();

      $messages = Messages::where('to_user_id', Auth::user()->id)
			->where('from_user_id', $id)
			->orWhere( 'from_user_id', Auth::user()->id )
			->where('to_user_id', $id)
      ->take(10)
			->orderBy('messages.updated_at', 'DESC')
			->get();

  	  $data = [];

  	  if ($messages->count()) {
  	      $data['reverse'] = collect($messages->values())->reverse();
  	  } else {
  	      $data['reverse'] = $messages;
  	  }

  	  $messages = $data['reverse'];
  		$counter = ($allMessages->count() - 10);

			//UPDATE MESSAGE 'READED'
			Messages::where('from_user_id',$id)
			   ->where('to_user_id', Auth::user()->id)
          ->where('status', 'new')
			    ->update(['status' => 'readed']);

      // Check if subscription exists
      $subscribedToYourContent = $this->subscribedToYourContent($user);

      $subscribedToMyContent = $this->subscribedToMyContent($user);

			return view('users.messages-show', [
            'messages' => $messages,
              'user' => $user,
              'counter' => $counter,
              'allMessages' => $allMessages->count(),
              'subscribedToYourContent' => $subscribedToYourContent,
              'subscribedToMyContent' => $subscribedToMyContent
          ]);

	}//<--- End Method messages

  /**
   * Load More Messages
   *
   * @param  \Illuminate\Http\Request  $request
   * @return Response
   */
  public function loadmore(Request $request)
	{
		$id   = $request->input('id');
		$skip = $request->input('skip');

    $user = User::whereId($id)->where('id', '<>', Auth::user()->id)->firstOrFail();

			$allMessages = Messages::where('to_user_id', Auth::user()->id)
			->where('from_user_id', $id)
			->orWhere( 'from_user_id', Auth::user()->id )
			->where('to_user_id', $id)
			->orderBy('messages.id', 'ASC')
			->get();

      $messages = Messages::where('to_user_id', Auth::user()->id)
			->where('from_user_id', $id)
			->orWhere( 'from_user_id', Auth::user()->id )
			->where('to_user_id', $id)
      ->skip($skip)
      ->take(10)
			->orderBy('messages.id', 'DESC')
			->get();

  	  $data = [];

  	  if ($messages->count()) {
  	      $data['reverse'] = collect($messages->values())->reverse();
  	  } else {
  	      $data['reverse'] = $messages;
  	  }

  	  $messages = $data['reverse'];
  		$counter = ($allMessages->count() - 10 - $skip);

    return view('includes.messages-chat', [
          'messages' => $messages,
          'user' => $user,
          'counter' => $counter,
          'allMessages' => $allMessages->count()
        ])->render();

	}//<--- End Method

  public function send(Request $request)
  {

    if ( ! Auth::check()) {
      return response()->json(array('session_null' => true));
    }

    $settings = AdminSettings::first();

    // PATHS
    $path = config('path.messages');

    $sizeAllowed = $settings->file_size_allowed * 1024;
    $dimensions = explode('x', $settings->min_width_height_image);

			 // Find user in Database
			 $user = User::findOrFail($request->get('id_user'));

       if ($request->hasFile('photo')) {

         $requiredMessage = null;

         $originalExtension = strtolower($request->file('photo')->getClientOriginalExtension());
         $getMimeType = $request->file('photo')->getMimeType();

         if ($originalExtension == 'mp3' && $getMimeType == 'application/octet-stream') {
           $audio = ',application/octet-stream';
         } else {
           $audio = null;
         }

         if ($originalExtension == 'mp4'
         || $originalExtension == 'mov'
         || $originalExtension == 'mp3'
         ) {
           $isImage = null;
       	} else {
           $isImage = '|dimensions:min_width='.$dimensions[0].'';
       	}
       } else {
         $isImage = null;
         $audio = null;
         $originalExtension = null;
         $requiredMessage = 'required|';
       }

       if ($request->hasFile('zip')) {
         $requiredMessage = null;
       }

       // Currency Position
       if ($settings->currency_position == 'right') {
         $currencyPosition =  2;
       } else {
         $currencyPosition =  null;
       }

			$messages = [
	            "required"    => trans('validation.required'),
	            "message.max"  => trans('validation.max.string'),
              'photo.dimensions' => trans('general.validate_dimensions'),
              'photo.mimetypes' => trans('general.formats_available'),
              'price.min' => trans('general.amount_minimum'.$currencyPosition, ['symbol' => $settings->currency_symbol, 'code' => $settings->currency_code]),
              'price.max' => trans('general.amount_maximum'.$currencyPosition, ['symbol' => $settings->currency_symbol, 'code' => $settings->currency_code]),
        	];

      // Setup the validator
			$rules = [
        'photo'  => 'mimetypes:image/jpeg,image/gif,image/png,video/mp4,video/quicktime,audio/mpeg,video/3gpp'.$audio.'|max:'.$settings->file_size_allowed.','.$isImage.'',
				'message'=> $requiredMessage.'|min:1|max:'.$settings->comment_length.'',
        'zip'    => 'mimes:zip|max:'.$settings->file_size_allowed.'',
        'price'  => 'numeric|min:'.$settings->min_ppv_amount.'|max:'.$settings->max_ppv_amount,
          ];

			$validator = Validator::make($request->all(), $rules, $messages);


			// Validate the input and return correct response
			if ($validator->fails()) {
			    return response()->json(array(
			        'success' => false,
			        'errors' => $validator->getMessageBag()->toArray(),
			    ));
			}

      // Upload File Zip
      if ($request->hasFile('zip')) {

        $fileZip         = $request->file('zip');
        $extension       = $fileZip->getClientOriginalExtension();
        $size            = Helper::formatBytes($fileZip->getSize(), 1);
        $originalName    = Helper::fileNameOriginal($fileZip->getClientOriginalName());
        $file            = strtolower(Auth::user()->id.time().Str::random(20).'.'.$extension);
        $format          = 'zip';

        $fileZip->storePubliclyAs($path, $file);

      }

        //============= Upload Media
        if ($request->hasFile('photo') && $isImage != null) {

          $photo       = $request->file('photo');
          $extension   = $photo->getClientOriginalExtension();
          $mimeType    = $request->file('photo')->getMimeType();
          $widthHeight = getimagesize($photo);
          $file        = strtolower(Auth::user()->id.time().Str::random(20).'.'.$extension);
          $size        = Helper::formatBytes($request->file('photo')->getSize(), 1);
          $format      = 'image';
          $originalName = $request->file('photo')->getClientOriginalName();
          $url         = ucfirst(Helper::urlToDomain(url('/')));

          set_time_limit(0);
          ini_set('memory_limit', '512M');

          if ($extension == 'gif' && $mimeType == 'image/gif') {
            $request->file('photo')->storePubliclyAs($path, $file);
          } else {
            //=============== Image Large =================//
            $img = Image::make($photo);

            $width     = $img->width();
            $height    = $img->height();
            $max_width = $width < $height ? 800 : 1400;

              if ($width > $max_width) {
              $scale = $max_width;
            } else {
              $scale = $width;
            }

            // Calculate font size
            if ($width >= 400 && $width < 900) {
              $fontSize = 16;
            } elseif ($width >= 800 && $width < 1200) {
              $fontSize = 20;
            } elseif ($width >= 1200 && $width < 2000) {
              $fontSize = 24;
            } elseif ($width >= 2000) {
              $fontSize = 32;
            } else {
              $fontSize = 0;
            }

            if ($settings->watermark == 'on') {
              $imageResize  = $img->orientate()->resize($scale, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
              })->text($url.'/'.auth()->user()->username, $img->width() - 20, $img->height() - 10, function($font)
                  use ($fontSize) {
                  $font->file(public_path('webfonts/arial.TTF'));
                  $font->size($fontSize);
                  $font->color('#eaeaea');
                  $font->align('right');
                  $font->valign('bottom');
              })->encode($extension);
            } else {
              $imageResize  = $img->orientate()->resize($scale, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
              })->encode($extension);
            }


              // Storage Image
              Storage::put($path.$file, $imageResize, 'public');
          }

        }//<====== End Upload Image

        //<----------- UPLOAD VIDEO
        if ($request->hasFile('photo')
            && $isImage == null
            && $originalExtension == 'mp4'
            || $originalExtension == 'mov'
      ) {

          $extension    = $request->file('photo')->getClientOriginalExtension();
          $file         = strtolower(Auth::user()->id.time().Str::random(20).'.'.$extension);
          $size         = Helper::formatBytes($request->file('photo')->getSize(), 1);
          $format      = 'video';
          $originalName = $request->file('photo')->getClientOriginalName();
          set_time_limit(0);

          //======= Storage Video
          $request->file('photo')->storePubliclyAs($path, $file);

        }//<====== End UPLOAD VIDEO

        //<----------- UPLOAD MUSIC
        if ($request->hasFile('photo')
        && $isImage == null
        && $originalExtension == 'mp3'
      ) {

          $extension    = $request->file('photo')->getClientOriginalExtension();
          $file         = strtolower(Auth::user()->id.time().Str::random(20).'.'.$extension);
          $size         = Helper::formatBytes($request->file('photo')->getSize(), 1);
          $format      = 'music';
          $originalName = $request->file('photo')->getClientOriginalName();
          set_time_limit(0);

          //======= Storage Video
          $request->file('photo')->storePubliclyAs($path, $file);

        }//<====== End UPLOAD MUSIC

				// Verify Conversation Exists
				$conversation = Conversations::where('user_1', Auth::user()->id)
  				->where('user_2', $request->get('id_user'))
  				->orWhere('user_1', $request->get('id_user'))
  				->where('user_2', Auth::user()->id)->first();

				$time = Carbon::now();

        if (! isset($conversation)) {
          $newConversation = new Conversations;
          $newConversation->user_1 = Auth::user()->id;
          $newConversation->user_2 = $request->get('id_user');
          $newConversation->updated_at = $time;
          $newConversation->save();

          $conversationID = $newConversation->id;

        } else {
          $conversation->updated_at = $time;
          $conversation->save();

          $conversationID = $conversation->id;
        }

        if ($request->hasFile('photo') || $request->hasFile('zip')) {
            $message = new Messages;
            $message->conversations_id = $conversationID;
    				$message->from_user_id     = Auth::user()->id;
    				$message->to_user_id       = $request->get('id_user');
            $message->message         =  trim(Helper::checkTextDb($request->get('message')));
            $message->file             = $file;
            $message->original_name    = $originalName;
            $message->format           = $format;
            $message->size             = $size;
            $message->updated_at       = $time;
            $message->price            = $request->price;
    				$message->save();

            return response()->json(array(
    				'success' => true,
            'last_id' => $message->id,
    				), 200);
        }

        if ($request->get('message')) {
            $message = new Messages;
            $message->conversations_id = $conversationID;
    				$message->from_user_id    = Auth::user()->id;
    				$message->to_user_id      = $request->get('id_user');
    				$message->message         = trim(Helper::checkTextDb($request->get('message')));
    				$message->updated_at      = $time;
            $message->price           = $request->price;
            $message->save();

            return response()->json(array(
    				'success' => true,
            'last_id' => $message->id,
    				), 200);
        }
}//<<--- End Method send()

  public function ajaxChat(Request $request)
  {
    if ( ! Auth::check()) {
      return response()->json(array('session_null' => true));
    }

      $_sql = $request->get('first_msg') == 'true' ? '=' : '>';

      $message = Messages::where('to_user_id', Auth::user()->id)
        ->where('from_user_id', $request->get('user_id'))
        ->where('id',$_sql, $request->get('last_id'))
        ->orWhere('from_user_id', Auth::user()->id )
        ->where('to_user_id', $request->get('user_id'))
        ->where('id',$_sql, $request->get('last_id'))
        ->orderBy('messages.id', 'ASC')
        ->get();

      $count = $message->count();
      $_array = array();

      if ($count != 0) {

        foreach ($message as $msg) {

          // UPDATE HOW READ MESSAGE
            if ($msg->to_user_id == Auth::user()->id) {
                 $readed = Messages::where('id', $msg->id)
                 ->where('to_user_id', Auth::user()->id)
                 ->where('status', 'new')
                 ->update(['status' => 'readed']);
            }

        $_array[] = view('includes.messages-chat', [
       			'messages' => $message,
       			'allMessages' => 0,
       			'counter' => 0
       			])->render();

        }//<--- foreach
      }//<--- IF != 0


      // Check User Online
      if (Cache::has('is-online-' . $request->get('user_id'))) {
        $userOnlineStatus = true;
      } else {
        $userOnlineStatus = false;
      }

      $user = User::findOrFail($request->get('user_id'));

      return response()->json(array(
        'total'    => $count,
        'messages' => $_array,
        'success' => true,
        'to' => $request->get('user_id'),
        'userOnline' => $userOnlineStatus,
        'last_seen' => date('c', strtotime($user->last_seen ?? $user->date))
        ), 200);
  }//<--- End Method ajaxChat

  public function delete(Request $request)
  {
   $message_id = $request->get('message_id');
   $path   = config('path.messages');

   $data = Messages::where('from_user_id', Auth::user()->id)
   ->where('id',$message_id)
   ->orWhere('to_user_id', Auth::user()->id)
   ->where('id',$message_id)->first();

   if (isset($data)) {

     Storage::delete($path.$data->file);

     $data->delete();

     $countMessages = Messages::where('conversations_id',$data->conversations_id)->count();

     if ($countMessages == 0) {
       $conversation = Conversations::find($data->conversations_id);
       $conversation->delete();
     }

     return response()->json( array( 'success' => true, 'total' => $countMessages ) );

   } else {
     return response()->json( array( 'success' => false, 'error' => trans('general.error') ) );
   }
 }//<--- End Method delete

 public function searchCreator(Request $request)
 {
   $settings = AdminSettings::first();
   $query = $request->get('user');
   $data = "";

   if ($query != '' && strlen($query) >= 2) {
     $sql = User::where('status','active')
         ->where('username','LIKE', '%'.$query.'%')
          ->where('id', '<>', Auth::user()->id)
          ->whereVerifiedId('yes')
          ->where('id', '<>', $settings->hide_admin_profile == 'on' ? 1 : 0)
          ->orWhere('status','active')
            ->where('name','LIKE', '%'.$query.'%')
              ->where('id', '<>', Auth::user()->id)
                ->whereVerifiedId('yes')
                ->where('id', '<>', $settings->hide_admin_profile == 'on' ? 1 : 0)
              ->orderBy('id','desc')
          ->take(10)
          ->get();

       if ($sql) {
         foreach ($sql as $user) {

           if (Cache::has('is-online-' . $user->id)) {
             $userOnlineStatus = 'user-online';
           } else {
             $userOnlineStatus = 'user-offline';
           }

           $name = $user->hide_name == 'yes' ? $user->username : $user->name;

           $data .= '<div class="card mb-2">
             <div class="list-group list-group-sm list-group-flush">
               <a href="'.url('messages/'.$user->id, $user->username).'" class="list-group-item list-group-item-action text-decoration-none p-2">
                 <div class="media">
                  <div class="media-left mr-3 position-relative '.$userOnlineStatus.'">
                      <img class="media-object rounded-circle" src="'.Helper::getFile(config('path.avatar').$user->avatar).'" width="45" height="45">
                  </div>
                  <div class="media-body overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                     <h6 class="media-heading mb-0 text-truncate">
                          '.$name.'
                      </h6>
                    </div>
                    <p class="text-truncate m-0 w-100 text-left">
                    <small>@'.$user->username.'</small>
                    </p>
                  </div>
              </div>
                </a>
             </div>
           </div>';
         }
         return $data;
        }
       }
     }// End Method

     public function deleteChat($id)
     {
      $path = config('path.messages');

      $messages = Messages::where('to_user_id', Auth::user()->id)
			->where('from_user_id', $id)
			->orWhere( 'from_user_id', Auth::user()->id )
			->where('to_user_id', $id)
			->get();

      if ($messages->count() != 0) {

        foreach ($messages as $msg) {

          Storage::delete($path.$msg->file);

          $msg->delete();
        }

          $conversation = Conversations::find($messages[0]->conversations_id);
          $conversation->delete();

        return redirect('messages');

      } else {
        return redirect('messages');
      }
    }//<--- End Method delete

    // Download File
    public function downloadFileZip($id)
   {
     $msg = Messages::findOrFail($id);

     if ($msg->to_user_id != auth()->user()->id && $msg->from_user_id != auth()->user()->id) {
       abort(404);
     }

     $pathFile = config('path.messages').$msg->file;
     $headers = [
       'Content-Type:' => ' application/x-zip-compressed',
       'Cache-Control' => 'no-cache, no-store, must-revalidate',
       'Pragma' => 'no-cache',
       'Expires' => '0'
     ];

     return Storage::download($pathFile, $msg->original_name.'.zip', $headers);
   } // End Method

   public function loadAjaxChat($id)
   {
     if ( ! request()->ajax()) {
       abort(401);
     }

     $user = User::whereId($id)->where('id', '<>', Auth::user()->id)->firstOrFail();

 		$allMessages = Messages::where('to_user_id', Auth::user()->id)
 		->where('from_user_id', $id)
 		->orWhere( 'from_user_id', Auth::user()->id )
 		->where('to_user_id', $id)
 		->orderBy('messages.id', 'ASC')
 		->get();

 		$messages = Messages::where('to_user_id', Auth::user()->id)
 		->where('from_user_id', $id)
 		->orWhere( 'from_user_id', Auth::user()->id )
 		->where('to_user_id', $id)
 		->take(10)
 		->orderBy('messages.id', 'DESC')
 		->get();

 		$data = [];

 		if ($messages->count()) {
 				$data['reverse'] = collect($messages->values())->reverse();
 		} else {
 				$data['reverse'] = $messages;
 		}

 		$messages = $data['reverse'];
 		$counter = ($allMessages->count() - 10);

 		return view('includes.messages-chat', [
 			'messages' => $messages,
 			'user' => $user,
 			'allMessages' => $allMessages->count(),
 			'counter' => $counter
 			])->render();
   }

}
