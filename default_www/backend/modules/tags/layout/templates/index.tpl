{include:file="{$BACKEND_CORE_PATH}/layout/templates/header.tpl"}
{include:file="{$BACKEND_CORE_PATH}/layout/templates/sidebar.tpl"}
		<td id="contentHolder">
			<div id="statusBar">
				<p class="breadcrumb">{$lblTags|ucfirst} &gt; {$lblIndex|ucfirst}</p>
			</div>

			{option:report}
			<div id="report">
				<div class="singleMessage successMessage">
					<p>{$reportMessage}</p>
				</div>
			</div>
			{/option:report}

			<div class="inner">
				{option:datagrid}
					<div class="datagridHolder">
						<div class="tableHeading">
							<h3>Tags</h3>
						</div>
						{$datagrid}
					</div>
				{/option:datagrid}
				{option:!datagrid}{$msgNoItems}{/option:!datagrid}
			</div>
		</td>
	</tr>
</table>
{include:file="{$BACKEND_CORE_PATH}/layout/templates/footer.tpl"}