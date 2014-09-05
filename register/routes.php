<?php

Route::get('/', function()
{
    return Response::json([
        'status' => 'OK',
        'couch'  => 'OK'
    ]);
});

Route::post('/{appName}', function($appName)
{
    $rules = [
        'username' => 'required|alphanum|min:5',
        'password' => 'required|min:8'
    ];
    $couchUsersConnection = DB::connection('couchUsers');
    $couchUsers = $couchUsersConnection->getCouchDB();

    $validator = Validator::make($data = Input::all(), $rules);
    if($validator->fails())
        return Response::json($validator->messages(), 400);

    $dbName = $appName . '.' . $data['username'];

    /**
     * Check User Exists
     */
    if($couchUsers->find('org.couchdb.user:' . $data['username']))
        return Response::json(['User already exists'], 400);
    
    /**
     * Create Database
     */
    $couchUsers->createDatabase($dbName);
    
    /**
     * Create User
     */
    $couchUsers->post([
        'name' => $data['username'],
        'type' => 'user',
        'roles' => [],
        'password' => $data['password']
    ]);
    
    /**
     * Add User To DB
     */
});