import { errorToJSON, toJSON } from '@/lib/common';
import { completion, createCompletion } from '@/lib/completions';
import { NextResponse } from 'next/server';

type RequestType = {
  courseworkTitle: string;
  courseName: string;
};

export async function POST(request: Request) {
  const data = (await request.json()) as RequestType;

  try {
    const chatCompletion = await createCompletion(
      completion({
        task: 'create_coursework',
        input: data,
        responseType: {
          description: 'string',
        },
      })
    );

    const { content } = chatCompletion.choices[0].message;

    return NextResponse.json(toJSON(content));
  } catch (error) {
    console.error(error);
    return NextResponse.json(errorToJSON(error));
  }
}
