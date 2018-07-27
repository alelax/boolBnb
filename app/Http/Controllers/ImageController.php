<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Image;

//add now
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;


class ImageController extends Controller
{
    public function fileStore(Request $request, $apartment_id)
    {
        
        $image = $request->file('file');
        $imageName = $image->getClientOriginalName();

        //add now
        Storage::putFileAs('public', new File($image), $imageName);

        /* $image->move(public_path('images'),$imageName); */
        
        $imageUpload = new Image();
        $imageUpload->title = $imageName;
        $imageUpload->apartament_id = $apartment_id; 
        $imageUpload->save();
        return response()->json(['success'=>$imageName]);
    }
    
    public function fileDestroy(Request $request)
    {
        $filename =  $request->get('filename');
        Image::where('title',$filename)->delete();
        
        //$path=public_path().'/images/'.$filename;
        $path=storage_path().'/app/public/'.$filename;        

        if (file_exists($path)) {
            unlink($path);
        }
        return $filename;       
        
    }
}