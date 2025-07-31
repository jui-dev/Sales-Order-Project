<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class SetProductMarkup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:set-markup {--markup=25 : Markup percentage to set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set markup percentage and enable auto-pricing for all products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $markup = (float) $this->option('markup');
        
        $this->info("Setting markup to {$markup}% and enabling auto-pricing for all products...");
        
        $products = Product::all();
        $updatedCount = 0;
        
        foreach ($products as $product) {
            $product->markup = $markup;
            $product->auto_pricing_enabled = true;
            
            // If the product has a purchase price, recalculate the selling price
            if ($product->purchase_price > 0) {
                $factor = $markup / 100;
                $product->selling_price = round($product->purchase_price + ($product->purchase_price * $factor), 2);
                $product->gross_profit = round($product->selling_price - $product->purchase_price, 2);
                $product->last_price_update = now();
            }
            
            $product->save();
            $updatedCount++;
            
            $this->line("Updated product: {$product->name} (ID: {$product->id})");
        }
        
        $this->info("✅ Successfully updated {$updatedCount} products");
        $this->info("📊 Markup set to: {$markup}%");
        $this->info("🔧 Auto-pricing enabled for all products");
        
        if ($updatedCount > 0) {
            $this->info("💡 Products with purchase prices have had their selling prices recalculated");
        }
        
        return Command::SUCCESS;
    }
} 