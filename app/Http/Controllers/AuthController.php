<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Exception;
use App\Models\Member;
use App\Models\Permission;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\MainBranch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{


    public function login(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'company') {
            $user = User::where('username', $request->username)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid username or password'
                ], 401);
            }

            // ✅ Subscription check using MainBranch model
            $branch = MainBranch::where('main_branch_id', $user->main_branch_id)->first();

            if (
                !$branch ||
                $branch->subscription_status != '1' ||
                // Carbon::parse($branch->subscription_end)->lt(now()) ||
                $user->status != '1')
             {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Company subscription expired or inactive.'
                ], 401);
            }

            // ✅ Generate token
            $token = auth('api')->login($user);

            return response()->json([
                'access_token' => $token,
                'user' => $user
            ]);
    }

    if ($type === 'member') {
        $cleanedAadhar = preg_replace('/\D/', '', $request->aadhar_no);
        $member = Member::where('m_no', $request->member_no)
                        ->where(DB::raw("REPLACE(aadhaarno, ' ', '')"), $cleanedAadhar)
                        ->where('isactive', 1)
                        ->first();

        if (! $member) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid Member No. or Aadhar No.'
            ], 401);
        }

        // ✅ Subscription check using MainBranch model
        // $branch = MainBranch::where('main_branch_id', $member->main_branch_id)->first();

        //  if (
        //         !$branch ||
        //         $branch->subscription_status != '1' 
             
        //     ) {
        //         return response()->json([
        //             'error' => 'Unauthorized',
        //             'message' => 'Company subscription expired or inactive.'
        //         ], 401);
        //     }

        $token = auth('member')->login($member); // Use member guard

        $member->role_id = $member->designation;
        $member->usertype = 'Member';
        $member->m_no_encpt = Crypt::encryptString($member->m_no);

        return response()->json([
            'access_token' => $token,
            'user' => $member
        ]);
    }

    return response()->json([
        'error' => 'Invalid login type'
    ], 400);
}


    // public function login(Request $request)
    // {
    //     $type = $request->input('type');

    //     if ($type === 'company') {
    //         $user = User::where('username', $request->username)->first();

    //         if (! $user || ! Hash::check($request->password, $user->password)) {
    //             return response()->json([
    //                 'error' => 'Unauthorized',
    //                 'message' => 'Invalid username or password'
    //             ], 401);
    //         }
            

    //         $token = auth('api')->login($user);

    //         return response()->json([
    //             'access_token' => $token,
    //             'user' => $user
    //         ]);
    //     }

    //    if ($type === 'member') {
    //         $member = Member::where('m_no', $request->member_no)
    //                         ->where('aadhaarno', $request->aadhar_no)
    //                         ->where('isactive', 1)
    //                         ->first();

    //         if (! $member) {
    //             return response()->json([
    //                 'error' => 'Unauthorized',
    //                 'message' => 'Invalid Member No. or Aadhar No.'
    //             ], 401);
    //         }

    //         $token = auth('member')->login($member); // Use member guard
    //         $member->role_id = $member->designation;
    //         $member->usertype = 'Member';
    //         $member->m_no_encpt = Crypt::encryptString($member->m_no);

    //         return response()->json([
    //             'access_token' => $token,
    //             'user' => $member
    //         ]);
    //     }

    //     return response()->json([
    //         'error' => 'Invalid login type'
    //     ], 400);

    // }


    public function me(Request $request)
    {
        // Try the "api" guard first:
        if ($user = $request->user('api')) {
            // $user->employeeid_encpt = Crypt::encryptString($user->employeeid);
            // return response()->json($user);

            $user->employeeid_encpt = Crypt::encryptString($user->employeeid);

        // Fetch permissions
        $permissions = $user->role
                            ->permissions()
                            ->pluck('name')
                            ->toArray();

        $user->permissions = $permissions;

        return response()->json(['user' => $user]);

        }
        
        // Then fallback to the "member" guard:
        if ($user = $request->user('member')) {
            $user->m_no_encpt = Crypt::encryptString($user->m_no);
            return response()->json($user);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user()
        ]);
    }
    //create insert
    public function addUser(Request $request) {
       $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;

          $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
                'phonenumber' => 'required|unique:users,phonenumber',
                'username' => 'required|unique:users,username',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }
        
        $data = [
            'main_branch_id' => auth()->user()->main_branch_id,
            'company_id'     => $companyId,
            'name'           => $request->name,
            'surname'        => $request->surname,
            'phonenumber'    => $request->phonenumber,
            'email'          => $request->email,
            'username'       => $request->username,
            'password'       => isset($request->password) ? Hash::make($request->password) : null,
            'usertype'       => $request->usertype,
            'role_id'        => $request->role_id,
            'doj' => $request->doj !== 'null' ? $request->doj : null,
            'dob' => $request->dob !== 'null' ? $request->dob : null,
            'adharno'        => $request->adharno,
            'panno'          => $request->panno,
            'hno'            => $request->hno,
            'landmark'       => $request->landmark,
            'colony'         => $request->colony,
            'dist'           => $request->dist,
            'mandal'         => $request->mandal,
            'pincode'        => $request->pincode,
            'acntno'         => $request->acntno,
            'ifsccode'       => $request->ifsccode,
            'acntname'       => $request->acntname,
            'bankname'       => $request->bankname,
            'status'         => $request->status ?? 1,
            'entryby'        => auth()->id() ?? null,
            'entrydate'      => now(),
        ];

        $user = User::create($data);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $extension = $image->getClientOriginalExtension();
                $filename = $user->m_mno . '.' . $extension;
                $path = $image->storeAs('uploads', $filename, 'public');
                $url = asset('storage/employees' . $path);
                $user->update([
                    'image' => $url
                ]);
            }

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    public function editUser(Request $request, $id) {
       try {
            $data = $request->all();

        

            $id = Crypt::decryptString($request->id);

            
              $validator = Validator::make($request->all(), [
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users')->ignore($id,'employeeid'),
                ],
                'phonenumber' => [
                    'required',
                    Rule::unique('users', 'phonenumber')->ignore($id,'employeeid'),
                ],
                'username' => [
                    'required',
                    Rule::unique('users', 'username')->ignore($id,'employeeid'),
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::where('employeeid', $id)->first();
            // $data = [
            //     'doj' => $request->filled('doj') ? $request->doj : null,
            //     'dob' => $request->filled('dob') ? $request->dob : null,
            // ];

            $data = $request->except('password'); // Exclude password initially

            // Update password only if provided
            if (!empty($request->password)) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

               if ($request->hasFile('image')) {
                $image = $request->file('image');
                $extension = $image->getClientOriginalExtension();
                $filename = $user->employeeid . '.' . $extension;
                $path = $image->storeAs('uploads/employees', $filename, 'public');
                $url = asset('storage/' . $path);
                $user->update([
                    'image' => $url
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $data,
            ]);
       }
       catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
       }
    }

    public function allusers(){
         $companyId = auth()->user()->role_id == 1
                    ?  cache()->get('superadmin_company_' . auth()->id(), 0)
                    : auth()->user()->company_id;

            $users = User::where('company_id', $companyId)
                ->where('status', 1)
                ->orderBy('employeeid', 'desc')
                ->get()
                ->map(function ($user) {
                    $user->employeeid_encpt = Crypt::encryptString($user->employeeid);
                    return $user;
                });
                return response()->json($users);
    }

    public function getuser(Request $request){
       try {
            $id = Crypt::decryptString($request->id);
            $user = User::where('employeeid', $id)->first();
            return response()->json([
                'success' => true,
                'user' => $user,
            ]);
       }
       catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
       }
    }


}