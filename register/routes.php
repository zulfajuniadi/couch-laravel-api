<?php

Route::get('/', function()
{
    return Response::json([
        'status' => 'OK'
    ]);
});

Route::post('/', function()
{
    $rules = [
        'appname'  => 'required|alpha_num|between:4,30',
        'username' => 'required|alpha_num|between:5,20',
        'password' => 'required|between:8,30'
    ];
    $couchUsersConnection = DB::connection('couchUsers');
    $couchUsers = $couchUsersConnection->getCouchDB();

    $data      = Input::all();
    $validator = Validator::make($data, $rules);
    if($validator->fails())
        return Response::json([
            'error_code'    => 100,
            'error_message' => $validator->failed()
        ], 400);

    $appname  = $data['appname'];
    $username = $data['username'];
    $dbName   = $appname . '__' . $username;

    /**
     * Check User Exists
     */
    $userId   = 'org.couchdb.user:' . $username;
    $response = $couchUsers->findDocument($userId);
    if($response->status !== 404)
        return Response::json([
            'error_code'    => 101,
            'error_message' => 'User already exists'
        ], 400);
    
    /**
     * Create User
     */
    try {
        $couchUser = $couchUsers->postDocument([
            '_id'      => $userId,
            'name'     => $username,
            'type'     => 'user',
            'roles'    => [$appname],
            'password' => $data['password']
        ]);
    } catch (Exception $e) {
        return Response::json([
            'error_code'    => 102,
            'error_message' => 'Unable to create user'
        ], 400);
    }
    
    /**
     * Create Database
     */
    try {
        $couchUsers->createDatabase($dbName);
    } catch (Exception $e) {
        return Response::json([
            'error_code'    => 103,
            'error_message' => 'Unable to create database'
        ], 400);
    }
    
    /**
     * Add User To DB
     */
    try {
        $adminUsername = Config::get('database.connections.couchUsers.user');
        $path = '/' . $dbName . '/_security';
        $data = [
            'admins'    => [
                'names' => [$adminUsername],
                'roles' => ['admins']
            ],
            'members'   => [
                'names' => [$username],
                'roles' => []
            ]
        ];
        $http = $couchUsers->getHttpClient();
        $http->request('PUT', $path, json_encode($data));
    } catch (Exception $e) {
        return Response::json([
            'error_code'    => 104,
            'error_message' => 'Unable to set database permissions'
        ], 400);
    }
    
    return Response::json($couchUser, 201);
});