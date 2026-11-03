<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleLine extends Model
{
    protected $fillable = ['sale_id', 'line_type', 'package_catalog_id', 'service_catalog_id', 'product_id', 'description', 'quantity', 'unit_price', 'line_total'];
}
