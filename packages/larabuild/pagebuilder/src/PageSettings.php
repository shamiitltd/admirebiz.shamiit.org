<?php

namespace Larabuild\Pagebuilder;

use Larabuild\Pagebuilder\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class PageSettings
{

    public $sectionId;
    public $directory;

    public function getPage($pageId)
    {

        if (config('cache.default') != 'null') {
            return Cache::rememberForever('pagebuilder__pageData_' . $pageId, function () use ($pageId) {
                return self::fetchPage($pageId);
            });
        } else {
            return self::fetchPage($pageId);
        }
    }

    public function getHeaderFooterPage($pageName)
    {
        $page = Page::where('name', $pageName)->first();
        if (config('cache.default') != 'null') {
            return Cache::rememberForever('pagebuilder__pageData_' . $page->id, function () use ($page) {
                return $page;
            });
        } else {
            return $page;
        }
    }

    private function fetchPage($pageId)
    {
        if (!empty($pageId)) {
            return  Page::find($pageId);
        }
    }

    public function storePage($request)
    {
        ($request->all());
        $pageData = [];
        foreach ($request->get('data') as $item) {
            $pageData[$item['name']] = $item['value'];
        }

        $pageData['slug'] = createPageSlug($pageData['slug']);
        $pageData['home_page'] = !empty($pageData['home_page']) == 'on' ? 1 : 0;
        if (config('app.app_sync')) {
            $pageData['home_page'] =  0;
        }

        // dd($pageData);
        $validator = Validator::make($pageData, [
            'name' => 'required',
            'title' => 'required',
            'description' => 'nullable',
            'slug' => 'required|unique:' . config('pagebuilder.db_prefix') . 'pages,slug,' . ($pageData['id'] ?? ''),
        ]);

        if ($validator->passes()) {
            if (Page::whereNull('slug')->first() && !empty($pageData['id']))
                $validator->errors()->add('slug', __('pagebuilder::pagebuilder.slug_error'));
            else {
                $pageData = sanitizeArray($pageData);
                if (!empty($pageData['home_page']))
                    if ($pageData['home_page'] == 1)
                        Page::where('home_page', 1)->where('school_id', auth()->user()->school_id)->update(['home_page' => 0]);

                if (empty($pageData['slug']))
                    $pageData['slug'] = null;
                
                Page::updateOrCreate(['id' => $pageData['id'] ?? null, 'school_id' => auth()->user()->school_id, 'created_by' => auth()->id()], $pageData);

                if (!empty($pageData['id']))
                    Cache::forget('pagebuilder__pageData_' . $pageData['id']);

                return response()->json(['success' => true]);
            }
        }

        return response()->json(['success' => false, 'error' => $validator->errors()->all()]);
    }

    public function getPageSettings($pageId)
    {

        return self::getPage($pageId)->settings ?? [];
    }

    public function getPageBySlug($slug)
    {
        return  Page::whereSlug($slug)->first();
    }

    public function getPageSectionSettings($pageId, $sectionId, $key)
    {
        $db_value = '';
        $pageSettings = self::getPage($pageId)->settings ?? [];
        $dbValue = [];
        if (!empty($pageSettings['section_data']) && array_key_exists($sectionId, $pageSettings['section_data'])) {
            $dbValue = $pageSettings['section_data'][$sectionId]['settings'][$key] ?? [];
            if (!empty($dbValue['is_array']) && $dbValue['is_array'] == '1') {
                foreach ($dbValue['value'] as $index => $value) {
                    if (!is_array($value) && self::isJSON($value))
                        $dbValue['value'][$index] = json_decode($value, true);
                    elseif (!is_array($value) && !self::isJSON($value))
                        $dbValue['value'][$index] = $value;
                    else
                        $dbValue['value'][$index] = self::jsonDecodedArr($value);
                }
            }
        }
        if (!empty($dbValue['value']))
            $db_value = $dbValue['value'];

        return $db_value;
    }

    private function jsonDecodedArr(&$arr)
    {

        foreach ($arr as &$el) {

            if (is_array($el)) {
                self::jsonDecodedArr($el);
            } else {
                if (self::isJSON($el)) {
                    $el = json_decode($el, true);
                }
            }
        }
        return  $arr;
    }

    /**
     * check string is json or not
     * @param $string String
     * @param mixed String $value
     * @return Void
     */
    private function isJSON($string)
    {

        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }

    public function setPageSettings($pageId, $pageSettings)
    {
        if (!empty($pageSettings))
            $pageSettings = sanitizeArray($pageSettings);
        Page::find($pageId)->update(['settings' => $pageSettings ?? []]);
        if (config('cache', true))
            Cache::forget('pagebuilder__pageData_' . $pageId);
    }

    public function updateStatus($request)
    {
        Page::find(gv($request, 'id'))->update(['status' => gv($request, 'status')]);
        return response()->json(['success' => true]);
    }
}
