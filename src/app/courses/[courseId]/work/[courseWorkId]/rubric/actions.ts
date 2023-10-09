'use server';

export async function addRubricItem(data: FormData) {
  const title = data.get('title');
  const description = data.get('description');
  const points = data.get('points');
}
