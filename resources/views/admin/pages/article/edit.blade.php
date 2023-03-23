@extends('admin.layouts.master')


@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Danh mục</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/admin">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('articles.index') }}">Danh sách bài viết</a></li>
                        <li class="breadcrumb-item active">Sửa bài viết</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Sửa bài viết</h3>
                </div>
                <!-- /.card-header -->
                <!-- form start -->
                <form action="{{ route('articles.update', $article->id) }}" method="post" enctype="multipart/form-data">
                    @method('put')
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="exampleInputEmail1">Tiêu đề bài viết</label>
                            <input type="text" class="form-control" name="title" id="exampleInputEmail1" placeholder="Tiêu đề bài viết" value="{{ old('title') ?? $article->title }}">
                            @error('title')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="exampleInputEmail1">Mô tả bài viết</label>
                            <input type="text" class="form-control" name="overall" id="exampleInputEmail1" placeholder="Mô tả bài viết" value="{{ old('overall') ?? $article->overall }}">
                            @error('overall')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="exampleInputEmail1">Ảnh bài viết</label>
                            <br>
                            <img src="{{ URL::asset('storage/images/' . $article->image) }}" height="100px" width="100px" />
                            <br>
                            <input type="file" class="form-control" name="image" id="exampleInputEmail1">
                            @error('image')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="exampleInputEmail1">Danh mục</label>
                            <select class="form-control" name="category_id" id="">
                                <option value=""></option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @if(old('category_id') ? old('category_id') == $category->id : $article->category_id == $category->id) selected @endif>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="exampleInputEmail1">Tag</label>
                            <select class="js-example-basic-multiple form-control" name="tag_ids[]" multiple="multiple" id="tag-ids">
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="exampleInputEmail1">Nội dung</label>
                            <div>
                                <textarea class="form-control" name="description" id="local-upload" cols="30" rows="10">{!! old('description') ?? $article->description !!}</textarea>
                            </div>
                            @error('description')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                    <!-- /.card-body -->

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            let tagIds = @json($initialTagIds);
            $('#tag-ids').select2().val(tagIds).trigger('change');
        })
    </script>
@endsection
