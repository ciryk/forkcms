{include:file="{$BACKEND_CORE_PATH}/layout/templates/header.tpl"}
{include:file="{$BACKEND_CORE_PATH}/layout/templates/sidebar.tpl"}
		<td id="contentHolder">
			<div id="statusBar">
				<p class="breadcrumb">{$lblTags|ucfirst} &gt; {$msgEditWithItem|sprintf:{$name}}</p>
			</div>

			<div class="inner">
				{form:edit}
					<div class="box">
						<div class="heading">
							&nbsp;
						</div>
						<div class="options">
							<p>
								<label for="tag">{$lblName|ucfirst}</label>
								{$txtName} {$txtNameError}
							</p>
						</div>
					</div>

					<div class="fullwidthOptions">
						<div class="buttonHolderRight">
							{$btnSave}
						</div>
					</div>
				{/form:edit}
			</div>
		</td>
	</tr>
</table>
{include:file="{$BACKEND_CORE_PATH}/layout/templates/footer.tpl"}