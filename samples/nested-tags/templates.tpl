<tpl:container>
	<tpl:template name="site:main"><!DOCTYPE html>
		<html>
			<head>
				<title>My Site: Home</title>
				<style type="text/css">
					body
					{
						font: 10pt sans-serif;
					}
					.bold
					{
						font-weight: bold;
					}
				</style>
			</head>
			<body>
				<tpl:content />
			</body>
		</html>
	</tpl:template>

	<tpl:template name="site:home">
		<p<tpl:if test=""> class="bold"</tpl:if>>Hello, this is the home page.  Isn't it pretty?</p>
	</tpl:template>
</tpl:container>