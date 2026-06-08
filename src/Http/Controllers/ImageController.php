<?php

namespace PDMFC\ImageEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDMFC\ImageEditor\Services\ImageService;

class ImageController extends Controller
{
    public function __construct(
        protected ImageService $imageService,
    ) {
    }

    private function imageEditRules(bool $forSave = false): array
    {
        $rules = [
            'user_id' => 'required',
            'image_url' => 'required|string',
            'brightness' => 'required|numeric',
            'contrast' => 'required|numeric',
            'saturation' => 'required|numeric|min:-1|max:1',
            'filter_preset' => 'nullable|string|in:neutral,bw,sepia,document,vivid',
            'gamma' => 'nullable|numeric|min:-100|max:100',
            'gamma_fine' => 'nullable|numeric|min:-50|max:50',
            'blur' => 'nullable|integer|min:0|max:100',
            'blur_region' => 'nullable|array',
            'blur_region.x' => 'required_with:blur_region|integer|min:0',
            'blur_region.y' => 'required_with:blur_region|integer|min:0',
            'blur_region.width' => 'required_with:blur_region|integer|min:1',
            'blur_region.height' => 'required_with:blur_region|integer|min:1',
            'blur_brush' => 'sometimes|boolean',
            'blur_mask' => 'nullable|string|max:6500000',
            'pixelate' => 'nullable|integer|min:0|max:100',
            'sharpen' => 'nullable|integer|min:-100|max:100',
            'pixelate_region' => 'nullable|array',
            'pixelate_region.x' => 'required_with:pixelate_region|integer|min:0',
            'pixelate_region.y' => 'required_with:pixelate_region|integer|min:0',
            'pixelate_region.width' => 'required_with:pixelate_region|integer|min:1',
            'pixelate_region.height' => 'required_with:pixelate_region|integer|min:1',
            'flip_horizontal' => 'sometimes|boolean',
            'flip_vertical' => 'sometimes|boolean',
            'rotation' => 'nullable|numeric',
            'crop' => 'nullable|array',
            'crop.x' => 'required_with:crop|integer|min:0',
            'crop.y' => 'required_with:crop|integer|min:0',
            'crop.width' => 'required_with:crop|integer|min:1',
            'crop.height' => 'required_with:crop|integer|min:1',
            'texts' => 'nullable|array|max:80',
            'texts.*.content' => 'required|string|max:2000',
            'texts.*.x' => 'required|integer|min:0|max:20000',
            'texts.*.y' => 'required|integer|min:0|max:20000',
            'texts.*.size' => 'nullable|integer|min:6|max:900',
            'texts.*.color' => 'nullable|string|max:32',
            'texts.*.bold' => 'sometimes|boolean',
            'texts.*.angle' => 'nullable|numeric|min:-360|max:360',
            'texts.*.align' => 'nullable|string|in:left,center,right',
            'texts.*.stroke_width' => 'nullable|integer|min:0|max:12',
            'texts.*.stroke_color' => 'nullable|string|max:32',
            'texts.*.background_color' => 'nullable|string|max:32',
            'texts.*.background_opacity' => 'nullable|integer|min:5|max:100',
            'texts.*.background_padding' => 'nullable|integer|min:0|max:48',
            'drawings' => 'nullable|array|max:500',
            'drawings.*.type' => 'required|string|in:line,arrow,rectangle,ellipse,circle,polygon,pen,bezier,pixel,fill',
            'drawings.*.points' => 'nullable|array|max:8000',
            'drawings.*.points.*.x' => 'required_with:drawings.*.points|integer|min:0|max:20000',
            'drawings.*.points.*.y' => 'required_with:drawings.*.points|integer|min:0|max:20000',
            'image_overlays' => 'nullable|array|max:20',
            'image_overlays.*.src' => 'required|string|max:6500000',
            'image_overlays.*.x' => 'required|integer|min:0',
            'image_overlays.*.y' => 'required|integer|min:0',
            'image_overlays.*.width' => 'required|integer|min:2|max:20000',
            'image_overlays.*.height' => 'required|integer|min:2|max:20000',
            'watermark' => 'nullable|array',
            'watermark.enabled' => 'sometimes|boolean',
            'watermark.type' => 'required_with:watermark|in:text,image',
            'watermark.text' => 'nullable|string|max:500',
            'watermark.position' => 'nullable|string|in:top-left,top-right,bottom-left,bottom-right,center',
            'watermark.opacity' => 'nullable|integer|min:5|max:100',
            'watermark.size' => 'nullable|integer|min:2|max:25',
            'watermark.color' => 'nullable|string|max:32',
            'watermark.margin' => 'nullable|integer|min:0|max:200',
            'watermark.src' => 'nullable|string|max:6500000',
            'watermark.image_scale' => 'nullable|integer|min:5|max:60',
            'zoom_layout' => 'nullable|array',
            'zoom_layout.canvas_width' => 'required_with:zoom_layout|integer|min:1|max:20000',
            'zoom_layout.canvas_height' => 'required_with:zoom_layout|integer|min:1|max:20000',
            'zoom_layout.base' => 'nullable|array',
            'zoom_layout.base.x' => 'required_with:zoom_layout.base|integer|min:0',
            'zoom_layout.base.y' => 'required_with:zoom_layout.base|integer|min:0',
            'zoom_layout.base.width' => 'required_with:zoom_layout.base|integer|min:2|max:20000',
            'zoom_layout.base.height' => 'required_with:zoom_layout.base|integer|min:2|max:20000',
            'zoom_layout.callouts' => 'nullable|array|max:12',
            'zoom_layout.callouts.*.src' => 'required|string|max:6500000',
            'zoom_layout.callouts.*.x' => 'required|integer|min:0',
            'zoom_layout.callouts.*.y' => 'required|integer|min:0',
            'zoom_layout.callouts.*.width' => 'required|integer|min:2|max:20000',
            'zoom_layout.callouts.*.height' => 'required|integer|min:2|max:20000',
            'pixelate_brush' => 'sometimes|boolean',
            'pixelate_mask' => 'nullable|string|max:6500000',
        ];

        if ($forSave) {
            $rules['save_mode'] = 'nullable|string|in:overwrite,copy';
            $rules['output_format'] = 'nullable|string|in:jpeg,png';
            $rules['output_quality'] = 'nullable|integer|min:1|max:100';
        }

        return $rules;
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate($this->imageEditRules());

        return response()->json($this->imageService->preview($request));
    }

    public function edit(Request $request): JsonResponse
    {
        $request->validate($this->imageEditRules(forSave: true));

        return response()->json($this->imageService->save($request));
    }
}
