<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Apartament;
use App\Model\Feature;
use App\Model\Image;
use App\Model\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ApartamentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {         
        /* dd($request); */
        $request->validate([
            'address' => 'required|string|max:255'
        ]);
            
        $featuresDB = Feature::all();
        //This variable will show on the result page the address searched
        $address_searched = $request->address;
        
        /* Get all apartment advertised */
        $apartments_advertised = new Apartament();
        $apartments_advertised = $apartments_advertised->where('is_active', 1);
        $apartments_advertised = $apartments_advertised->whereHas('advertisements', function($query){
            $query->where('valid_until', '>', Carbon::now());
        })->get();

        

        
        $distanceToSearch = 20; //Distance search default value
        
        if(!empty($request->distance))
        {
            $distanceToSearch = $request->distance;
        }

        //This array will contain all apartments will show as results
        $apartmentsToShow = [];

        //This statment add the apartments advertised in first position in apartmentsToShow array 
        if ($apartments_advertised->isNotEmpty()) {
            
            foreach ($apartments_advertised as $apartment) {

                $distance = $this->distance($request->lat, $request->lng, $apartment['latitude'], $apartment['longitude']);
                
                if( $distance < ($distanceToSearch+10) ){

                    $thumbnail = $this->setThumbnail($apartment->id);
        
                    $apartmentsToShow[] = [
                        'apartment' => $apartment,
                        'distance' => $distance,
                        'thumbnail' => $thumbnail,
                        'is_advertised' => true
                    ];
                }
            }
        }


        $apartments = new Apartament();

        if (!empty($request->beds_number)) {        
            $apartments = $apartments->where('beds_number', '>=', $request->beds_number);
        }
        if (!empty($request->bathrooms_number)) {        
            $apartments = $apartments->where('bathrooms_number', '>=', $request->bathrooms_number);
        }

        /*  Define temp array that will contain all features sent by reqeust or nothing
            it will be used to check checked checkbox and to fill a $checked_and_notChecked array
            with correct value to send with ajax
        */
        $checked_and_notChecked = [];

        $control_array;
        
        if (!empty($request->features)) {
            $control_array = $request->features;   
        } else {
            $control_array = [];
        }

        /* Create am array that contain all features id, and isChecked key to differentiate wich features are sent checke from welcome view */
        foreach ($featuresDB as $f) {
            if( in_array($f['id'], $control_array ) ) {
                $temp = [
                    'id' => $f['id'],
                    'name' => $f['name'],
                    'isChecked' => true
                ];
            } else {
                $temp = [
                    'id' => $f['id'],
                    'name' => $f['name'],
                    'isChecked' => false
                ];
            }
            $checked_and_notChecked[] = $temp;
        }

        //Return on the welcome page if a user change features value manually
        if (!empty($request->features)) {
            foreach ($request->features as $featureId) {
                
                if($featuresDB->contains('id', $featureId))
                {
                    $apartments = $apartments->whereHas('features', function ($query) use($featureId) {
                        $query->where('feature_id', $featureId);
                    });
                }
                else
                {
                    return view('publicViews.welcome', ['features' => $featuresDB]);
                }
            }
        }

        $apartments = $apartments->where('is_active', 1);
        
        $apartments = $apartments->get();       

        $not_advertised_apartment = [];

        foreach ($apartments as $apartment) {

            $distance = $this->distance($request->lat, $request->lng, $apartment->latitude, $apartment->longitude);
            
            if($distance < $distanceToSearch){
                
                $thumbnail = $this->setThumbnail($apartment->id);                
                
                if(count($apartmentsToShow) != 0) {
                    
                    $i = 0;
                    $itemIsFound = false;    

                    do {
                        if ( ($apartmentsToShow[$i]['apartment']->id) == ($apartment->id) ) {
                            $itemIsFound = true;
                        } else {
                            $i++;
                        }
                    } while ( !($itemIsFound) && ($i < count($apartmentsToShow)) );

                    if (!($itemIsFound)) {
                        $not_advertised_apartment[] = [
                            'apartment' => $apartment,
                            'distance' => $distance,
                            'thumbnail' => $thumbnail,
                            'is_advertised' => false
                        ];
                    }
                } else {
                    $not_advertised_apartment[] = [
                        'apartment' => $apartment,
                        'distance' => $distance,
                        'thumbnail' => $thumbnail,
                        'is_advertised' => false
                    ];
                }                 
            }
        }

        //Sort result by distance
        usort($not_advertised_apartment, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        foreach ($not_advertised_apartment as $n_a_a) {
            $apartmentsToShow[] = $n_a_a;
        }

        //Check of the request is an ajax request
        if($request->ajax()){ 
            $html = view('components.apartments_cards', ['apartmentsToShow' => $apartmentsToShow])->render();
            return response()->json([
                "log" => "Chiamata AJAX",
                /* 'res' => $results, */
                'html' => $html      
            ]);
        }       

        return view('publicViews.apartmentFinder', [
            'apartmentsToShow' => $apartmentsToShow,
            'address_searched' => $address_searched,
            'request' => $request,
            'features' => $featuresDB,
            'check_notcheck_feat' => $checked_and_notChecked,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $userId = Auth::user()->id;
        
        $request->validate([
            'title' => 'required|string|max:255',
            'beds_number' => 'required|integer|min:1',
            'bathrooms_number' => 'required|integer|min:1',
            'price' => 'required|integer|min:1'
        ]);

        $requestArray = $request->toArray();

        $new_apartment = new Apartament();
        $new_apartment->fill($requestArray);  
        $new_apartment->is_active = 1;        
        $new_apartment->latitude = $request->lat;
        $new_apartment->longitude = $request->lng;
        $new_apartment->user_id = $userId;

        $features = $request->features;

        /* dd($features); */

        $new_apartment->save();
                
        $new_apartment->features()->sync($features);

        $request->session()->flash('status', 'L\'appartamento è stato aggiunto correttamente');
        $request->session()->flash('error', 'Si è verificato un errore. Compila nuovamente il form');

        
        return redirect()->route('home');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {   
        /*
            If user is logged the form on his apartments notice 
            will be set disabled. The control below return the id or -1
            at showApartment view
        */
        $userId = Auth::user();
        if (is_null($userId) ) {
            $userId = -1;
        } else {
            $userId = Auth::user()->id;            
        }

        $apart = Apartament::find($id);
        $feat = $apart->features;
        $images = Image::where('apartament_id', $apart->id)->get();

        $images_url_container = [];

        if($images->isNotEmpty()){
            foreach ($images as $image) {
                
                $image_exist = Storage::disk('public')->exists($image->filename);            
                if ($image_exist) {
                    $images_url_container[] = $image->filename;
                } 
            }        
        }
        else{
            $images_url_container[] = 'placeholder.jpg';
        }

        return view('publicViews.showApartment', [
            'user_logged_id' => $userId,
            'apartment' => $apart, 
            'features' => $feat,
            'images' => $images_url_container
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {       
        $modified_apartment = Apartament::find($id);
        /*
            If isActive exist the update method will change only this value, otherwise
            all other fields will be update
        */
        if (!$request->has('isActive')) {

            $request->validate([
                'title' => 'required|string|max:255',
                'beds_number' => 'required|integer|min:1',
                'bathrooms_number' => 'required|integer|min:1',
                'price' => 'required|integer|min:1'
            ]);
            
            $requestArray = $request->toArray();
            
            $modified_apartment->fill($requestArray);              
            $modified_apartment->latitude = $request->lat;
            $modified_apartment->longitude = $request->lng;

        } else {            
            $modified_apartment->is_active = $request->isActive;  
        }
        
        $features = $request->features;

        $modified_apartment->save();

        $modified_apartment->features()->sync($features);

        $request->session()->flash('status', 'Le modifiche sono state eseguite correttamente');
        $request->session()->flash('error', 'Si è verificato un errore. Compila nuovamente il form');

        return redirect()->route('home');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $apartment_to_delete = Apartament::find($id);

        $apartment_to_delete->features()->detach(); //Reletions into pivot table will be delete
        $apartment_to_delete->advertisements()->detach();
        
        $related_images = Image::where('apartament_id', $id)->get();
        
        foreach ($related_images as $i) {
            $path = storage_path().'/app/public/'.$i->filename;        
            Image::where('filename',$i->filename)->delete();
            
            if (file_exists($path)) {
                unlink($path);
            }
        }
        
        $related_messages = Message::where('apartament_id', $id)->get();
        foreach ($related_messages as $m) {
            Message::where('id', $m->id)->delete();
        }
         

        $apartment_to_delete->delete();

        return redirect()->route('home');
    }

    // Method to calculate distance between two point by lat and lng
    private function distance($lat1, $lon1, $lat2, $lon2) {
       
        $pi80 = M_PI / 180;
        $lat1 *= $pi80;
        $lon1 *= $pi80;
        $lat2 *= $pi80;
        $lon2 *= $pi80;
    
        $r = 6372.797; // mean radius of Earth in km
        $r_meters = $r * 1000;
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        $km = $r * $c;
        
        return $km;
    }

    //Method to find and set the apartment thumbnail.
    private function setThumbnail($apartment_id){
        $thumbnail = Image::where('apartament_id', $apartment_id)->first();
    
        if( !(is_null($thumbnail))  && (Storage::disk('public')->exists($thumbnail->filename)) ){
                $thumbnail = $thumbnail->filename;     
            }
        else{
            $thumbnail = 'placeholder.jpg';
        }

        return $thumbnail;
    }
}
