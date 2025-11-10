namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public static function move(array $movements)
    {
        DB::transaction(function () use ($movements) {
            foreach ($movements as $m) {
                /** @var Item $item */
                $item = Item::findOrFail($m['item_id']);
                $qty = $m['qty'];

                if ($m['type'] === 'decrease') {
                    if ($item->stock < $qty) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok {$item->name} tidak mencukupi (tersisa {$item->stock})"
                        ]);
                    }
                    $item->decrement('stock', $qty);
                } elseif ($m['type'] === 'increase') {
                    $item->increment('stock', $qty);
                }
            }
        });
    }
}
