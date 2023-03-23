<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\BookingRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Mail\BookingInformation;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tour;
use App\Models\Transition;
use App\Services\ArticleService;
use App\Services\BookingService;
use App\Services\CategoryService;
use App\Services\DiscountService;
use App\Services\HotelService;
use App\Services\TagService;
use Illuminate\Http\Request;
use App\Services\TourService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    protected $tourService;
    protected $articleService;
    protected $categoryService;
    protected $tagService;
    protected $userService;
    protected $discountService;
    protected $hotelService;
    protected $bookingService;

    public function __construct(
        TourService $tourService,
        ArticleService $articleService,
        CategoryService $categoryService,
        TagService $tagService,
        UserService $userService,
        DiscountService $discountService,
        HotelService $hotelService,
        BookingService $bookingService
    ) {
        $this->tourService = $tourService;
        $this->articleService = $articleService;
        $this->categoryService = $categoryService;
        $this->tagService = $tagService;
        $this->userService = $userService;
        $this->discountService = $discountService;
        $this->hotelService = $hotelService;
        $this->bookingService = $bookingService;
    }

    public function index()
    {
        $tours = $this->tourService->getFeatureTours();
        $articles = $this->articleService->getLatestArticlesByLimit(2);
        $reviews = Review::take(4)->get();

        return view('client.index', compact('tours', 'articles', 'reviews'));
    }

    public function tourList(Request $request)
    {
        $tours = $this->tourService->findByCondition($request->all(), 6);

        $latestTours = $this->tourService->getLatestTourByLimit(3);

        return view('client.tour_list', compact('tours', 'latestTours'));
    }

    public function tourDetail($id)
    {
        $tour = $this->tourService->find($id);

        return view('client.tour_detail', compact('tour'));
    }

    public function articleList(Request $request)
    {
        $categories = $this->categoryService->getAll();
        $latestArticles = $this->articleService->getLatestArticlesByLimit(3);
        $tags = $this->tagService->getAll();
        if (isset($request->category_id)) {
            $articles = $this->articleService->getByCategoryId($request->category_id, 8);
        } elseif (isset($request->tag_id)) {
            $articles = $this->articleService->getByTagId($request->tag_id, 8);
        } elseif (isset($request->search)) {
            $articles = $this->articleService->search($request->search, 8);
        } else {
            $articles = $this->articleService->getPaginate(8);
        }

        return view('client.article_list', compact('articles', 'categories', 'latestArticles', 'tags'));
    }

    public function articleDetail($id)
    {
        $article = $this->articleService->find($id);
        $categories = $this->categoryService->getAll();
        $latestArticles = $this->articleService->getLatestArticlesByLimit(3);
        $tags = $this->tagService->getAll();

        return view('client.article_detail',  compact('article', 'categories', 'latestArticles', 'tags'));
    }

    public function getLogin()
    {
        return view('client.login');
    }

    public function postLogin(LoginRequest $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->remember_me)) {
            $user = $this->userService->findByField('email', $request->email)->first();
            if ($user->role == 2) {
                return redirect('/');
            } else {
                return redirect()->back()->with('notify', 'Sai email hoặc mật khẩu');
            }
        } else {
            return redirect()->back()->with('notify', 'Sai email hoặc mật khẩu');
        }
    }

    public function booking($tourId)
    {
        $tour = $this->tourService->find($tourId);

        return view('client.booking', compact('tour'));
    }

    public function confirmBooking(BookingRequest $request, $tourId)
    {
        $tour = $this->tourService->find($tourId);
        $booking = $request;
        $hotel = $request->hotel_id ? $this->hotelService->find($request->hotel_id) : null;

        return view('client.confirm_booking', compact('tour', 'booking', 'hotel'));
    }

    public function getRegister()
    {
        return view('client.register');
    }

    public function postRegister(RegisterRequest $request)
    {
        $data = $request->all();
        $data['role'] = 2;
        $result = $this->userService->store($data);
        if ($result) {
            return redirect('/login')->with('notify', 'Đăng ký thành công');
        }

        return redirect('/login')->with('notify', 'Đăng ký thất bại');
    }

    public function logout()
    {
        Auth::logout();

        return redirect('/');
    }

    public function getDiscount($code)
    {
        $discount = $this->discountService->findByField('code', $code)->first();
        if ($discount->start_date <= date('Y-m-d') && $discount->end_date >= date('Y-m-d') && $discount->remain_number > 0) {
            return response()->json([
                'status' => true,
                'id' => $discount->id,
                'discount_rate' => $discount->discount_rate
            ]);
        }

        return response()->json(['status' => false]);
    }

    public function completeBooking(Request $request)
    {
        // parse_str(explode('?', Session::all()['_previous']['url'] ?? [])[1] ?? [], $sessionData);

        $bookingData['tour_id'] = $request->tour_id;
        $bookingData['user_id'] = Auth::check() ? Auth::user()->id : null;
        $bookingData['hotel_id'] = $request->hotel_id;
        $bookingData['booking_person_phone'] = $request->booking_person_phone;
        $bookingData['booking_person_name'] = $request->booking_person_name;
        $bookingData['booking_person_email'] = $request->booking_person_email;
        $bookingData['booking_person_address'] = $request->booking_person_address;
        $bookingData['total_price'] = $request->booking_price;
        $bookingData['start_date'] = $request->start_date;
        $bookingData['adult_number'] = $request->adult_number;
        $bookingData['children_number'] = $request->children_number;
        $bookingData['baby_number'] = $request->baby_number;
        $bookingData['discount_id'] = $request->discount_id;
        $bookingData['note'] = $request->note;
        $bookingData['status'] = 1;

        if ((isset($request->resultCode) && $request->resultCode == '0')) // momo
        {
            $bookingData['payment_status'] = 3;
            $bookingData['payment'] = 3;
            $newBooking = $this->bookingService->store($bookingData);
            $paymentMethod = 'Momo';
            $email = $request->booking_person_email;
            Mail::to($email)->send(new BookingInformation($bookingData));

            Transition::create([
                'booking_id' => $newBooking->id,
                'transaction_code' => $request->transId,
                'amount' => $request->amount,
                'payment_method' => 3
            ]);

            return view('client.complete_booking', compact('email', 'paymentMethod'));
        }
        else {
            // return redirect()->back()->with('notify', 'Thanh toán thất bại.');
            $tour = $this->tourService->find($request->tour_id);
            $booking = $request;
            $hotel = $request->hotel_id ? $this->hotelService->find($request->hotel_id) : null;
            return view('client.confirm_booking', compact('tour', 'booking', 'hotel'));
        }
    }

    public function rawPayment(Request $request)
    {
        $request['is_raw'] = 'is_raw';

        $redirectRoute = route("complete_booking") . "?" . http_build_query($request->all());

        return redirect($redirectRoute);
    }

    public function momoPayment(Request $request)
    {
        $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
        $partnerCode = 'MOMOBKUN20180529';
        $accessKey = 'klm05TvNBzhg7h7j';
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderInfo = "Thanh toán qua MoMo";
        $amount = $request->booking_price;
        $orderId = time() ."";
        $redirectUrl = route('complete_booking') . '?' . http_build_query($request->all());
        $ipnUrl = "https://webhook.site/b3088a6a-2d17-4f8d-a383-71389a6c600b";
        $extraData = "";

        $requestId = time() . "";
        $requestType = "payWithATM";

        //before sign HMAC SHA256 signature
        $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        $data = array('partnerCode' => $partnerCode,
            'partnerName' => "Test",
            "storeId" => "MomoTestStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
        );
        $result = $this->execPostRequest($endpoint, json_encode($data));
        $jsonResult = json_decode($result, true);  // decode json

        //Just a example, please check more in there
        return redirect()->to($jsonResult['payUrl']);
    }

    public function execPostRequest($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        //execute post
        $result = curl_exec($ch);
        //close connection
        curl_close($ch);
        return $result;
    }

    public function bookingHistory()
    {
        $bookings = Booking::where('user_id', Auth::id())->orderBy('created_at', 'desc')->get();

        return view('client.history', compact('bookings'));
    }

    public function getRemainSlot(Request $request)
    {
        $tourSlot = Tour::find($request->tour_id)->people_limit;
        $sameBooking = Booking::whereDate('start_date', Carbon::createFromFormat('d/m/Y', $request->start_date)->format('Y-m-d'))->where('tour_id', $request->tour_id);
        $adultSlot = $sameBooking->sum('adult_number');
        $childrenSlot = $sameBooking->sum('children_number');
        $babySlot = $sameBooking->sum('baby_number');
        $placedSlot = $adultSlot + $childrenSlot + $babySlot;
        $remainSlot = $tourSlot - $placedSlot;

        return response()->json([
            'remain_slot' => $remainSlot
        ]);
    }

    public function getProfile()
    {
        $user = $this->userService->find(Auth::id());

        return view('client.profile', compact('user'));
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $result = $this->userService->update(Auth::id(), $request->all());

        if ($result) {
            return redirect()->back()->with('notify', 'Cập nhật thông tin thành công.');
        }

        return redirect()->back()->with('notify', 'Cập nhật thông tin thất bại.');
    }

    public function cancelBooking($id)
    {
        $booking = $this->bookingService->find($id);
        $booking->status = 3;
        $booking->save();

        return redirect()->back()->with('notify', 'Hủy tour thành công');
    }

    public function comment(Request $request)
    {
        $reviewData = [
            'tour_id' => $request->tour_id,
            'user_id' => Auth::id(),
            'stars' => $request->stars ?? 5,
            'description' => $request->description,
            'is_show' => 1
        ];

        $review = Review::create($reviewData);

        return response()->json([
            'user_name' => $review->user->name,
            'created_at' => \Carbon\Carbon::parse($review->created_at)->format('d/m/Y'),
            'stars' => $review->stars,
            'description' => $review->description
        ]);
    }
}
