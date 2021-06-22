<?php

namespace App\Http\Controllers;

use App\Models\Basket;
use App\Models\Order;
use App\Models\Product;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = auth()->user();
        if ($user->hasRole('web-developer')) return view('dev');
        if ($user->hasRole('project-manager')) return view('admin',['statuses'=>Status::all()]);
        return view('home');
    }

    public function admin()
    {

        if (!auth()->user()->hasRole('project-manager'))
        return view('home');

        $statuses = Status::all();
        return view('admin',['statuses'=>$statuses]);
    }

    public function add_basket($id)
    {
        if ($id)
        {
            if (DB::select('select count(*) as count from baskets where user_id=?',[auth()->user()->id])[0]->count==0){
                $basket=new Basket();
                $basket->user_id=auth()->user()->id;
                $basket->basket=json_encode(array($id));
                $basket->save();
                // $basket=
            }
            else{
                $arr = json_decode(DB::select('select basket from baskets where user_id=?',[auth()->user()->id])[0]->basket);
                array_push($arr,$id);
                Basket::Where('user_id',auth()->user()->id)->update(['basket' => $arr]);
            }
        }
        $all_products = Product::paginate(10);
        return view('all_Products',['products'=>$all_products]);
    }

    public function orderbuy($id)
    {
        if ($id)
        {
            if (DB::select('select count(*) as count from baskets where user_id=?',[auth()->user()->id])[0]->count==0){
                $basket=new Basket();
                $basket->user_id=auth()->user()->id;
                $basket->basket=json_encode(array($id));
                $basket->save();
                // $basket=
            }
            else{
                $arr = json_decode(DB::select('select basket from baskets where user_id=?',[auth()->user()->id])[0]->basket);
                array_push($arr,$id);
                Basket::Where('user_id',auth()->user()->id)->update(['basket' => $arr]);
            }
        }
        $products = json_decode(DB::select('select basket from baskets where user_id = ?', [auth()->user()->id])[0]->basket);
        for($i=0;$i<count($products);$i++){
            $products[$i]=DB::select('select * from products where id = ?', [$products[$i]])[0];
        }
        return view('order',['products'=>$products]);
    }
    public function order()
    {
        if (DB::select('select basket from baskets where user_id = ?', [auth()->user()->id])){
            $products = json_decode(DB::select('select basket from baskets where user_id = ?', [auth()->user()->id])[0]->basket);
            for($i=0;$i<count($products);$i++){
                $products[$i]=DB::select('select * from products where id = ?', [$products[$i]])[0];
            }
            return view('order',['products'=>$products]);}
        else return view('order');

    }

    public function all_Products()
    {
        $all_products = Product::paginate(10);
        return view('all_Products',['products'=>$all_products]);
    }

    public function ordered(Request $request)
    {

        if ($request){
            $validate = $request->validate([
                'lastname'=>'required|max:191',
                'firstname'=>'required|max:191',
                'number'=>'required|min:3|max:191']
            );
            $order=new Order();
            $order->last_name=$request->lastname;
            $order->first_name=$request->firstname;
            $order->phone_number=$request->number;
            $products = json_decode(DB::select('select basket from baskets where user_id = ?', [auth()->user()->id])[0]->basket);
            $new_basket_products=[];
            for($i=0,$j=0;$i<count($products);$i++)
            {
                if (count($request->product)>$j && $request->product[$j]==$i){
                    $j++;
                    continue;
                }
                array_push($new_basket_products,$products[$i]);
            }
            $products=$new_basket_products;
            for($i=0,$j=0;$i<count($products);$i++)
            {
                $products[$i]=DB::select('select * from products where id = ?', [$products[$i]])[0];
            }
            $product_names=array();
            $product_prices=array();
            foreach($products as $product)
            {
                array_push($product_names,$product->name);
                array_push($product_prices,$product->price);
            }
            $order->product_name=json_encode($product_names);
            $order->price=json_encode($product_prices);
            // $order->price=$product_prices;
            $order->id_status=1;
            $order->save();

        }
        Basket::Where('user_id',auth()->user()->id)->delete();
        $all_products = Product::paginate(10);
        return view('all_Products',['products'=>$all_products]);
    }

    public function status()
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');
        $statuses = Status::all();
        return view('Status',['statuses'=>$statuses]);
    }

    public function addStatus(Request $request)
    {

        // status
        $validate=$request->validate([
            'add_status'=>'required|max:191',
        ]);
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');

        $status=new Status();
        $status->status=$request->add_status;
        $status->save();

        $statuses = Status::all();
        return view('Status',['statuses'=>$statuses]);
    }

    public function deleteStatus(Request $request)
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');
        Status::Where('status',$request->input('delete_status'))->delete();
        $statuses = Status::all();
        return view('Status',['statuses'=>$statuses]);
    }

    public function changeStatus(Request $request)
    {
        $validate=$request->validate([
            'status'=>'required|max:191',
        ]);
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');
        Status::Where('status',$request->input('change_status'))->update(['status' => $request->input('status')]);
        $statuses = Status::all();
        return view('Status',['statuses'=>$statuses]);
    }

    public function product(Request $request)
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');

        if ($request->add_name && $request->add_price && $request->main_image)
        {
            // $ext = $request->main_image->extension();
            $fileNameToStore = "/products/product_".((DB::select('select max(id) as max from products')[0]->max)+1).".".$request->main_image->extension();
            $request->main_image->storeAs('public/', $fileNameToStore);

            $product=new Product();
            $product->name=$request->add_name;
            $product->price=$request->add_price;
            $product->image='storage'.$fileNameToStore;
            $product->save();
        }


        $all_products = Product::paginate(10);
        return view('Product',['products'=>$all_products]);
    }

    public function addProduct()
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');
        return view('add_Product');
    }

    public function deleteProduct($id)
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');

        Product::Where('id',$id)->delete();

        $all_products = Product::paginate(10);
        return view('Product',['products'=>$all_products]);
    }

    public function changeProduct(Request $request,$id)
    {
        $validate=$request->validate([
            'change_name'=>'max:191',
            'change_price'=>'max:191',
            'change_image'=>'image',
        ]);

        if (!auth()->user()->hasRole('project-manager'))
        return view('home');
        // Product::Where('name',$request->input('change_product'))->update(['name' => $request->input('product')]);


        if ($request->change_name)
        {
            Product::Where('id',$id)->update(['name' => $request->change_name]);
        }
        if ($request->change_price)
        {
            Product::Where('id',$id)->update(['price' => $request->change_price]);
        }
        if ($request->change_image)
        {
            $fileNameToStore = "/products/product_".$id.".".$request->change_image->extension();
            $request->change_image->storeAs('public/', $fileNameToStore);
        }

        $all_products = Product::paginate(10);
        return view('change_Product',['products'=>$all_products,'id'=>$id]);
    }

    public function orders(Request $request)
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');

        if($request->id)
        {
            $validate = $request->validate([
                'first_name'=>'required|max:191',
                'last_name'=>'required|max:191',
                'phone_number'=>'required|min:3|max:191']
                );
            $products=DB::select('select product_name,price from orders where id=?', [$request->id])[0];
            $product_names=json_decode($products->product_name);
            $product_prices=json_decode($products->price);
            $new_product_names=[];
            $new_product_prices=[];
            for($i=0,$j=0;$i<count($product_names);$i++){
                if (count($request->product)>$j && $request->product[$j]==$i){
                    $j++;
                    continue;
                }
                array_push($new_product_names,$product_names[$i]);
                array_push($new_product_prices,$product_prices[$i]);
            }

            Order::Where('id',$request->id)->update(['last_name'=>$request->last_name,'first_name'=>$request->first_name,'phone_number'=>$request->phone_number,'id_status'=>$request->status,'product_name'=>json_encode($new_product_names),'price'=>json_encode($new_product_prices)]) ;

        }

        $all_orders = Order::all();
        return view('orders',['orders'=>$all_orders]);
    }

    public function change_order($id)
    {
        if (!auth()->user()->hasRole('project-manager'))
        return view('home');

        // $all_orders = Order::find($id);
        return view('change_order',['order'=>Order::find($id)]);
    }
}
