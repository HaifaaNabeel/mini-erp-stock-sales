<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above case, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'auth/login';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Authentication & Dashboard
$route['login'] = 'auth/login';
$route['logout'] = 'auth/logout';
$route['dashboard'] = 'dashboard/index';

// User Management (Admin only)
$route['users'] = 'users/index';
$route['users/create'] = 'users/create';
$route['users/edit/(:num)'] = 'users/edit/$1';
$route['users/change_password/(:num)'] = 'users/change_password/$1';
$route['users/toggle_status/(:num)'] = 'users/toggle_status/$1';
$route['users/warehouses/(:num)'] = 'users/warehouses/$1';

// F04: Categories Management (Admin only)
$route['categories'] = 'categories/index';
$route['categories/create'] = 'categories/create';
$route['categories/edit/(:num)'] = 'categories/edit/$1';
$route['categories/toggle_status/(:num)'] = 'categories/toggle_status/$1';

// F04: Warehouses Management & User Assignments (Admin only)
$route['warehouses'] = 'warehouses/index';
$route['warehouses/my'] = 'warehouses/my';
$route['warehouses/create'] = 'warehouses/create';
$route['warehouses/edit/(:num)'] = 'warehouses/edit/$1';
$route['warehouses/toggle_status/(:num)'] = 'warehouses/toggle_status/$1';
$route['warehouses/assignments/(:num)'] = 'warehouses/assignments/$1';

// F04: Products Management (Admin only)
$route['products'] = 'products/index';
$route['products/create'] = 'products/create';
$route['products/edit/(:num)'] = 'products/edit/$1';
$route['products/toggle_status/(:num)'] = 'products/toggle_status/$1';

// F05: Warehouse Stock Management (Admin, Warehouse, Sales)
$route['stock'] = 'stock/index';
$route['stock/edit_reorder/(:num)'] = 'stock/edit_reorder/$1';

// F06: Stock Movements (Admin, Warehouse, Sales)
$route['stock/movements'] = 'stock_movements/index';
$route['stock/movements/in'] = 'stock_movements/create_in';
$route['stock/movements/adjust'] = 'stock_movements/create_adjustment';
$route['stock/movements/ajax_get_stock'] = 'stock_movements/ajax_get_stock';

// F07: Customers Management (Admin and Sales)
$route['customers'] = 'customers/index';
$route['customers/create'] = 'customers/create';
$route['customers/edit/(:num)'] = 'customers/edit/$1';
$route['customers/activate/(:num)'] = 'customers/activate/$1';
$route['customers/deactivate/(:num)'] = 'customers/deactivate/$1';
$route['customers/toggle_status/(:num)'] = 'customers/toggle_status/$1';

// F07: Sales Invoices (Admin and Sales)
$route['sales/invoices'] = 'sales_invoices/index';
$route['sales/invoices/create'] = 'sales_invoices/create';
$route['sales/invoices/ajax_get_products'] = 'sales_invoices/ajax_get_products';
$route['sales/invoices/print/(:num)'] = 'sales_invoices/print_invoice/$1';
$route['sales/invoices/view/(:num)'] = 'sales_invoices/view/$1';

