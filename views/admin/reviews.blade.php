@include('_tabs')

<section class="section">
	<h2>{{ $property->title }} — {{ $lang->lodging_review }}</h2>

	<table class="x_table x_table-striped">
		<thead>
			<tr>
				<th scope="col" class="nowr">{{ $lang->lodging_review_score }}</th>
				<th scope="col">{{ $lang->lodging_review_content }}</th>
				<th scope="col">{{ $lang->lodging_review_reply }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_sale_status }}</th>
			</tr>
		</thead>
		<tbody>
			@foreach($review_list as $review)
			<tr>
				<td class="nowr">
					&#9733; {{ $review->score }}<br />
					<span class="x_help-inline">{{ $review->writer_name }}</span><br />
					<span class="x_help-inline">{{ zdate($review->regdate, 'y.m.d') }}</span>
				</td>
				<td>{{ $review->content }}</td>
				<td colspan="2">
					<form action="./" method="post">
						<input type="hidden" name="module" value="lodging" />
						<input type="hidden" name="act" value="procLodgingAdminReviewReply" />
						<input type="hidden" name="review_srl" value="{{ $review->review_srl }}" />
						<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
						<textarea name="reply" rows="2" class="x_full-width" placeholder="{{ $lang->lodging_review_reply_ph }}">{{ $review->reply }}</textarea>
						<div style="margin-top:6px;display:flex;gap:10px;align-items:center">
							<label class="x_inline"><input type="radio" name="status" value="public" @if($review->status === 'public') checked @endif /> {{ $lang->lodging_review_public }}</label>
							<label class="x_inline"><input type="radio" name="status" value="hidden" @if($review->status === 'hidden') checked @endif /> {{ $lang->lodging_review_hidden }}</label>
							<button type="submit" class="x_btn">{{ $lang->cmd_save }}</button>
						</div>
					</form>
				</td>
			</tr>
			@endforeach
			@if(!count($review_list))
			<tr><td colspan="4" class="x_text-center">{{ $lang->lodging_no_review }}</td></tr>
			@endif
		</tbody>
	</table>

	@if($page_navigation)
	<div class="x_page-navigation">
		@foreach($page_navigation as $page_no)
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'reviews', 'property_srl', $property->property_srl, 'page', $page_no) }}" @if((int)$page === (int)$page_no) class="x_active" @endif>{{ $page_no }}</a>
		@endforeach
	</div>
	@endif
</section>
