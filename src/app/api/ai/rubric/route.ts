import { errorToJSON, toJSON } from '@/lib/common';
import { completion, createCompletion } from '@/lib/completions';
import prisma from '@/lib/db';
import { revalidatePath } from 'next/cache';
import { NextResponse } from 'next/server';
import { z } from 'zod';

const inputSchema = z.object({
  maxPoints: z.number().optional(),
  courseName: z.string(),
  courseworkTitle: z.string(),
  courseWorkDescription: z.string(),
});

const outputSchema = z.object({
  rubric: z.array(
    z.object({
      title: z.string(),
      description: z.string(),
      points: z.number(),
    })
  ),
});

export async function POST(request: Request) {
  const data = await request.json();
  const input = inputSchema.parse(data);

  try {
    const chatCompletion = await createCompletion(
      completion({
        task: 'create_rubric',
        input: input,
        responseType: {
          rubric: [
            {
              title: 'string',
              description: 'string',
              points: 'number',
            },
          ],
        },
      })
    );

    const { content } = chatCompletion.choices[0].message;
    return Response.json(outputSchema.parse(toJSON(content)));
  } catch (error) {
    console.error(error);
    return NextResponse.json(errorToJSON(error));
  }
}
