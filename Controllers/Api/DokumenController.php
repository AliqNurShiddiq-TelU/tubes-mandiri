<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DokumenController extends Controller
{
    /**
     * POST /api/dokumen
     * Upload dokumen (PDF/Word/PPT)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_dokumen' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx|max:10240', // Max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $file = $request->file('file_dokumen');
            $originalName = $file->getClientOriginalName();
            // Simpan dengan nama unik
            $filename = time() . '_' . preg_replace('/\s+/', '_', $originalName);
            
            // Simpan ke storage/app/public/documents
            $path = $file->storeAs('public/documents', $filename);
            
            // Simpan path relatif ke database
            $dbPath = 'documents/' . $filename;
            $extension = $file->getClientOriginalExtension();

            $dokumen = Dokumen::create([
                'file_path' => $dbPath,
                'tipe_file' => $extension,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil diupload',
                'data' => $dokumen // ID ini nanti dipakai untuk create SubMateri
            ], 201);

        } catch (\Exception $e) {
            Log::error('Upload dokumen error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server Error'], 500);
        }
    }

    public function update(Request $request, $id_dokumen)
    {
        $dokumen = Dokumen::find($id_dokumen);

        if (!$dokumen) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        try {
            // Validasi (File bersifat nullable/opsional saat update)
            $validator = Validator::make($request->all(), [
                'file_dokumen' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // Cek apakah user mengupload file baru?
            if ($request->hasFile('file_dokumen')) {
                
                // 1. HAPUS FILE LAMA
                // Kita tambahkan prefix 'public/' karena di database tersimpan 'documents/...'
                // sedangkan root Storage ada di 'storage/app'
                if ($dokumen->file_path && Storage::exists('public/' . $dokumen->file_path)) {
                    Storage::delete('public/' . $dokumen->file_path);
                }

                // 2. UPLOAD FILE BARU (Logika copy-paste dari function Store Anda)
                $file = $request->file('file_dokumen');
                $originalName = $file->getClientOriginalName();
                
                // Buat nama unik
                $filename = time() . '_' . preg_replace('/\s+/', '_', $originalName);
                
                // Simpan ke storage/app/public/documents
                $file->storeAs('public/documents', $filename);
                
                // Path untuk database
                $dbPath = 'documents/' . $filename;
                $extension = $file->getClientOriginalExtension();

                // 3. UPDATE DATA DI DATABASE
                $dokumen->file_path = $dbPath;
                $dokumen->tipe_file = $extension;
                
                // Simpan perubahan
                $dokumen->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil diperbarui',
                'data'    => $dokumen
            ], 200);

        } catch (\Exception $e) {
            Log::error('Update dokumen error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server Error'], 500);
        }
    }


    public function destroy($id_dokumen)
    {
        $dokumen = Dokumen::find($id_dokumen);
        if (!$dokumen) return response()->json(['message' => 'Not found'], 404);

        // Hapus file fisik
        if (Storage::exists('public/' . $dokumen->file_path)) {
            Storage::delete('public/' . $dokumen->file_path);
        }

        $dokumen->delete();
        return response()->json(['success' => true, 'message' => 'Dokumen dihapus']);
    }
}