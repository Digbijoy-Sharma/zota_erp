<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Events\PurchaseCreatedOrModified;
use App\Product;
use App\TaxRate;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Unit;
use App\Variation;
use DB;
use Excel;
use Illuminate\Http\Request;

class ImportPurchasesController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(
        ProductUtil $productUtil,
        BusinessUtil $businessUtil,
        TransactionUtil $transactionUtil,
        ModuleUtil $moduleUtil
    ) {
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of past imports.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $imported_purchases = Transaction::where('business_id', $business_id)
                            ->where('type', 'purchase')
                            ->whereNotNull('import_batch')
                            ->with(['sales_person'])
                            ->select('id', 'import_batch', 'import_time', 'ref_no', 'created_by')
                            ->orderBy('import_batch', 'desc')
                            ->get();

        $imported_purchases_array = [];
        foreach ($imported_purchases as $purchase) {
            $imported_purchases_array[$purchase->import_batch]['import_time'] = $purchase->import_time;
            $imported_purchases_array[$purchase->import_batch]['created_by'] = $purchase->sales_person->user_full_name;
            $imported_purchases_array[$purchase->import_batch]['ref_nos'][] = $purchase->ref_no;
        }

        $import_fields = $this->__importFields();

        return view('import_purchases.index')->with(compact('imported_purchases_array', 'import_fields'));
    }

    /**
     * Preview the uploaded file's data before importing. Columns must
     * match the template exactly; see __validateHeaders().
     *
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        if (! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->businessUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $business_id = request()->session()->get('user.business_id');

        if ($request->hasFile('purchases_file')) {
            $file_name = time().'_'.$request->purchases_file->getClientOriginalName();
            $request->purchases_file->storeAs('temp', $file_name);

            $parsed_array = $this->__parseData($file_name);

            try {
                $this->__validateHeaders($parsed_array[0]);
            } catch (\Exception $e) {
                @unlink(public_path('uploads/temp/'.$file_name));

                return redirect('import-purchases')->with('notification', ['success' => 0, 'msg' => $e->getMessage()]);
            }

            $business_locations = BusinessLocation::forDropdown($business_id);

            return view('import_purchases.preview')->with(compact('parsed_array', 'file_name', 'business_locations'));
        }
    }

    /**
     * Ensures the uploaded file's header row matches the template exactly
     * (same columns, same order) since columns are read by fixed position.
     *
     * @param  array  $headers
     * @return void
     */
    private function __validateHeaders($headers)
    {
        $expected_labels = array_values(array_map(function ($field) {
            return $field['label'];
        }, $this->__importFields()));

        $headers = array_values($headers);

        if (count($headers) !== count($expected_labels)) {
            throw new \Exception(__('lang_v1.import_purchase_invalid_template'));
        }

        foreach ($expected_labels as $i => $expected) {
            if (strcasecmp(trim($headers[$i] ?? ''), trim($expected)) !== 0) {
                throw new \Exception(__('lang_v1.import_purchase_invalid_template'));
            }
        }
    }

    public function __parseData($file_name)
    {
        $array = Excel::toArray([], public_path('uploads/temp/'.$file_name))[0];

        //remove blank columns from headers
        $headers = array_filter($array[0]);

        //Remove header row
        unset($array[0]);
        $parsed_array[] = $headers;
        foreach ($array as $row) {
            $temp = [];
            foreach ($row as $k => $v) {
                if (array_key_exists($k, $headers)) {
                    $temp[] = $v;
                }
            }
            $parsed_array[] = $temp;
        }

        return $parsed_array;
    }

    /**
     * Import purchases to database
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        if (! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\ImportPurchasesController::class, 'index']));
        }

        $file_name = $request->input('file_name');
        $file_path = public_path('uploads/temp/'.$file_name);

        try {
            DB::beginTransaction();

            $location_id = $request->input('location_id');

            $parsed_array = $this->__parseData($file_name);
            $this->__validateHeaders($parsed_array[0]);
            //Remove header row
            unset($parsed_array[0]);
            $formatted_purchase_data = $this->__formatPurchaseData($parsed_array);

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            $this->__importPurchases($formatted_purchase_data, $business_id, $location_id);

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.purchase_imported_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            @unlink($file_path);

            return redirect('import-purchases')->with('notification', $output);
        }

        @unlink($file_path);

        return redirect('import-purchases')->with('status', $output);
    }

    private function __importPurchases($formatted_data, $business_id, $location_id)
    {
        $import_batch = Transaction::where('business_id', $business_id)
                            ->where('type', 'purchase')
                            ->max('import_batch');

        if (empty($import_batch)) {
            $import_batch = 1;
        } else {
            $import_batch = $import_batch + 1;
        }

        $now = \Carbon::now()->toDateTimeString();
        $date_format = session('business.date_format') ?: 'Y-m-d';
        $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
        $order_statuses = array_keys($this->productUtil->orderStatuses());
        $user_id = auth()->user()->id;

        $row_index = 2;
        foreach ($formatted_data as $data) {
            $purchase_lines = [];
            $total_before_tax = 0;
            $total_tax = 0;

            foreach ($data as $line_data) {
                if (! empty($line_data['sku'])) {
                    $variation = Variation::where('sub_sku', trim($line_data['sku']))
                        ->whereHas('product', function ($query) use ($business_id) {
                            $query->where('business_id', $business_id);
                        })
                        ->with(['product'])
                        ->first();

                    $product = ! empty($variation) ? $variation->product : null;
                } else {
                    $product = Product::where('business_id', $business_id)
                                    ->where('name', $line_data['product'])
                                    ->with(['variations'])
                                    ->first();
                    $variation = ! empty($product) ? $product->variations->first() : null;
                }

                if (empty($variation) || empty($product)) {
                    throw new \Exception(__('lang_v1.import_purchase_product_not_found', ['row' => $row_index, 'product_name' => $line_data['product'], 'sku' => $line_data['sku']]));
                }

                $pp_without_discount = (float) $line_data['unit_cost_before_discount'];
                $discount_percent = ! empty($line_data['discount_percent']) ? (float) $line_data['discount_percent'] : 0;
                $purchase_price = $pp_without_discount - ($pp_without_discount * ($discount_percent / 100));

                $tax_id = null;
                $item_tax = 0;
                if (! empty($line_data['tax_name'])) {
                    $tax = TaxRate::where('business_id', $business_id)
                                ->where('name', $line_data['tax_name'])
                                ->first();

                    if (empty($tax)) {
                        throw new \Exception(__('lang_v1.import_purchase_tax_not_found', ['row' => $row_index, 'tax_name' => $line_data['tax_name']]));
                    }
                    $tax_id = $tax->id;
                    $item_tax = $this->productUtil->calc_percentage($purchase_price, $tax->amount);
                }

                $purchase_price_inc_tax = $purchase_price + $item_tax;

                //resolve an optional purchase unit (e.g. "Box") different from
                //the product's own base unit. Quantity/cost columns above are
                //interpreted in terms of this unit; createOrUpdatePurchaseLines()
                //converts them to base-unit equivalents using sub_unit_id.
                $sub_unit_id = null;
                if (! empty($line_data['unit'])) {
                    $unit_name = trim($line_data['unit']);
                    $unit = Unit::where('business_id', $business_id)
                                ->where(function ($q) use ($unit_name) {
                                    $q->where('actual_name', $unit_name)->orWhere('short_name', $unit_name);
                                })
                                ->first();

                    if (empty($unit)) {
                        throw new \Exception(__('lang_v1.import_purchase_unit_not_found', ['row' => $row_index, 'unit_name' => $unit_name]));
                    }

                    if ($unit->id != $product->unit_id) {
                        $sub_unit_id = $unit->id;
                    }
                }

                //check if mfg/exp dates are valid, then reformat to the business date format
                //so that createOrUpdatePurchaseLines's internal uf_date() conversion works correctly
                $mfg_date = null;
                if (! empty($line_data['mfg_date'])) {
                    try {
                        $mfg_date = \Carbon::parse($line_data['mfg_date'])->format($date_format);
                    } catch (\Exception $e) {
                        throw new \Exception(__('lang_v1.invalid_date_format_at', ['row' => $row_index]));
                    }
                }
                $exp_date = null;
                if (! empty($line_data['exp_date'])) {
                    try {
                        $exp_date = \Carbon::parse($line_data['exp_date'])->format($date_format);
                    } catch (\Exception $e) {
                        throw new \Exception(__('lang_v1.invalid_date_format_at', ['row' => $row_index]));
                    }
                }

                $quantity = (float) $line_data['quantity'];

                $purchase_lines[] = [
                    'product_id' => $product->id,
                    'variation_id' => $variation->id,
                    'product_unit_id' => $product->unit_id,
                    'quantity' => $quantity,
                    'pp_without_discount' => $pp_without_discount,
                    'discount_percent' => $discount_percent,
                    'purchase_price' => $purchase_price,
                    'purchase_price_inc_tax' => $purchase_price_inc_tax,
                    'item_tax' => $item_tax,
                    'purchase_line_tax_id' => $tax_id,
                    'lot_number' => ! empty($line_data['lot_number']) ? $line_data['lot_number'] : null,
                    'mfg_date' => $mfg_date,
                    'exp_date' => $exp_date,
                    'sub_unit_id' => $sub_unit_id,
                ];

                $total_before_tax += $quantity * $purchase_price;
                $total_tax += $quantity * $item_tax;

                $row_index++;
            }

            $first_line = $data[0];

            //get supplier
            $contact = null;
            if (! empty($first_line['supplier_phone'])) {
                $contact = Contact::where('business_id', $business_id)
                                ->whereIn('type', ['supplier', 'both'])
                                ->where('mobile', $first_line['supplier_phone'])
                                ->first();
            } elseif (! empty($first_line['supplier_email'])) {
                $contact = Contact::where('business_id', $business_id)
                                ->whereIn('type', ['supplier', 'both'])
                                ->where('email', $first_line['supplier_email'])
                                ->first();
            }
            if (empty($contact) && ! empty($first_line['supplier_name'])) {
                //suppliers created as a business (rather than an individual)
                //store their display name in supplier_business_name and
                //leave name blank, so match against either column.
                $contact = Contact::where('business_id', $business_id)
                                ->whereIn('type', ['supplier', 'both'])
                                ->where(function ($q) use ($first_line) {
                                    $q->where('name', $first_line['supplier_name'])
                                        ->orWhere('supplier_business_name', $first_line['supplier_name']);
                                })
                                ->first();
            }
            if (empty($contact)) {
                $contact = Contact::create([
                    'business_id' => $business_id,
                    'type' => 'supplier',
                    'name' => $first_line['supplier_name'],
                    'email' => $first_line['supplier_email'] ?: null,
                    'mobile' => $first_line['supplier_phone'] ?: '',
                    'created_by' => $user_id,
                ]);
            }

            //transaction date
            $transaction_date = $now;
            if (! empty($first_line['date'])) {
                try {
                    $transaction_date = \Carbon::parse($first_line['date'])->toDateTimeString();
                } catch (\Exception $e) {
                    throw new \Exception(__('lang_v1.invalid_date_format_at', ['row' => $row_index]));
                }
            }

            //status
            $status = 'received';
            if (! empty($first_line['status'])) {
                $status = strtolower(trim($first_line['status']));
                if (! in_array($status, $order_statuses)) {
                    throw new \Exception(__('lang_v1.invalid_purchase_status_at', ['row' => $row_index, 'status' => $first_line['status']]));
                }
            }

            $final_total = $total_before_tax + $total_tax;

            $transaction_data = [
                'business_id' => $business_id,
                'location_id' => $location_id,
                'type' => 'purchase',
                'status' => $status,
                'payment_status' => 'due',
                'contact_id' => $contact->id,
                'transaction_date' => $transaction_date,
                'total_before_tax' => $total_before_tax,
                'tax_amount' => 0,
                'discount_type' => 'fixed',
                'discount_amount' => 0,
                'shipping_charges' => 0,
                'final_total' => $final_total,
                'exchange_rate' => 1,
                'import_batch' => $import_batch,
                'import_time' => $now,
                'created_by' => $user_id,
            ];

            $transaction_data['ref_no'] = $first_line['ref_no'];

            $transaction = Transaction::create($transaction_data);

            $this->productUtil->createOrUpdatePurchaseLines($transaction, $purchase_lines, $currency_details, false);

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            $this->transactionUtil->activityLog($transaction, 'added');

            PurchaseCreatedOrModified::dispatch($transaction);
        }
    }

    private function __formatPurchaseData($imported_data)
    {
        //Columns are read by fixed position (matching __importFields()'s
        //order) since __validateHeaders() already enforced the uploaded
        //file's header row matches the template exactly.
        $field_keys = array_keys($this->__importFields());
        $field_count = count($field_keys);

        $formatted_array = [];
        $row_index = 2;
        foreach ($imported_data as $key => $value) {
            //Skip fully blank rows (e.g. trailing blank rows some
            //spreadsheet tools leave in a file's used range).
            if (count(array_filter($value, function ($v) {
                return $v !== null && $v !== '';
            })) === 0) {
                $row_index++;

                continue;
            }

            $value = array_pad(array_slice($value, 0, $field_count), $field_count, null);
            $row = array_combine($field_keys, $value);
            $formatted_array[$key] = $row;

            if (empty($row['ref_no'])) {
                throw new \Exception(__('lang_v1.ref_no_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($row['supplier_name'])) {
                throw new \Exception(__('lang_v1.supplier_name_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($row['product']) && empty($row['sku'])) {
                throw new \Exception(__('lang_v1.product_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($row['quantity'])) {
                throw new \Exception(__('lang_v1.quantity_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($row['unit_cost_before_discount'])) {
                throw new \Exception(__('lang_v1.unit_cost_cannot_be_empty_in_row', ['row' => $row_index]));
            }

            $row_index++;
        }

        $formatted_data = [];
        foreach ($formatted_array as $row) {
            $formatted_data[$row['ref_no']][] = $row;
        }

        return $formatted_data;
    }

    private function __importFields()
    {
        return [
            'ref_no' => ['label' => __('purchase.ref_no'), 'instruction' => __('lang_v1.ref_no_groups_purchase_lines_instruction')],
            'supplier_name' => ['label' => __('lang_v1.supplier_name'), 'instruction' => __('lang_v1.required')],
            'supplier_phone' => ['label' => __('lang_v1.supplier_phone_number')],
            'supplier_email' => ['label' => __('lang_v1.supplier_email')],
            'date' => ['label' => __('purchase.purchase_date')],
            'status' => ['label' => __('sale.status')],
            'product' => ['label' => __('product.product_name'), 'instruction' => __('lang_v1.either_product_name_or_sku_required')],
            'sku' => ['label' => __('product.sku'), 'instruction' => __('lang_v1.either_product_name_or_sku_required')],
            'quantity' => ['label' => __('purchase.purchase_quantity'), 'instruction' => __('lang_v1.required')],
            'unit' => ['label' => __('lang_v1.purchase_import_unit'), 'instruction' => __('lang_v1.import_purchase_unit_instruction')],
            'unit_cost_before_discount' => ['label' => __('lang_v1.unit_cost_before_discount'), 'instruction' => __('lang_v1.required')],
            'discount_percent' => ['label' => __('lang_v1.discount_percent')],
            'tax_name' => ['label' => __('lang_v1.tax_name')],
            'lot_number' => ['label' => __('lang_v1.lot_number')],
            'mfg_date' => ['label' => __('product.mfg_date')],
            'exp_date' => ['label' => __('product.exp_date')],
        ];
    }

    /**
     * Deletes all purchases from a batch
     *
     * @return \Illuminate\Http\Response
     */
    public function revertPurchaseImport($batch)
    {
        if (! auth()->user()->can('purchase.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $purchases = Transaction::where('business_id', $business_id)
                                ->where('type', 'purchase')
                                ->where('import_batch', $batch)
                                ->get();

            DB::beginTransaction();
            foreach ($purchases as $purchase) {
                $result = $this->transactionUtil->deletePurchase($business_id, $purchase->id);

                //deletePurchase() returns success=false (rather than throwing)
                //when blocked by an existing return or lot numbers already used
                //in a sale - turn that into a hard failure here so a blocked
                //purchase can't be silently skipped while the rest of the
                //batch gets deleted and the user is told "reverted successfully".
                if (empty($result['success'])) {
                    throw new \Exception(__('lang_v1.import_revert_blocked', ['ref_no' => $purchase->ref_no, 'reason' => $result['msg']]));
                }
            }

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.import_reverted_successfully')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect('import-purchases')->with('status', $output);
    }
}
