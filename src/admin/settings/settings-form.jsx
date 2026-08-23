function isFieldVisible( field, data ) {
	return typeof field.isVisible === 'function'
		? field.isVisible( data )
		: true;
}

function withGetValue( field ) {
	if ( field.getValue ) {
		return field;
	}
	return {
		...field,
		getValue: ( { item } ) => item?.[ field.id ],
	};
}

function FormField( { field, data, onChange } ) {
	const resolved = withGetValue( field );
	if ( ! isFieldVisible( resolved, data ) || ! resolved.Edit ) {
		return null;
	}
	const Edit = resolved.Edit;
	return (
		<div className="bei-form-field">
			<Edit data={ data } field={ resolved } onChange={ onChange } />
		</div>
	);
}

function resolveLayout( form, fields ) {
	const fieldMap = Object.fromEntries(
		fields.map( ( field ) => [ field.id, field ] )
	);

	return ( form?.fields || [] ).map( ( item ) => {
		if ( typeof item === 'string' ) {
			return { type: 'field', field: fieldMap[ item ] };
		}
		return {
			type: 'section',
			id: item.id,
			label: item.label,
			children: ( item.children || [] )
				.map( ( id ) => fieldMap[ id ] )
				.filter( Boolean ),
		};
	} );
}

export function SettingsForm( { data, fields, form, onChange } ) {
	const items = resolveLayout( form, fields );

	return (
		<div className="bei-form">
			{ items.map( ( item, index ) => {
				if ( item.type === 'section' ) {
					const visible = item.children.filter( ( field ) =>
						isFieldVisible( withGetValue( field ), data )
					);
					if ( ! visible.length ) {
						return null;
					}
					return (
						<section
							key={ item.id || `section-${ index }` }
							className="bei-settings-section"
						>
							{ item.label && <h2>{ item.label }</h2> }
							<div className="bei-form-fields">
								{ visible.map( ( field ) => (
									<FormField
										key={ field.id }
										field={ field }
										data={ data }
										onChange={ onChange }
									/>
								) ) }
							</div>
						</section>
					);
				}

				if ( ! item.field ) {
					return null;
				}

				return (
					<FormField
						key={ item.field.id }
						field={ item.field }
						data={ data }
						onChange={ onChange }
					/>
				);
			} ) }
		</div>
	);
}
